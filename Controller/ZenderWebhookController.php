<?php

namespace MauticPlugin\MauticZenderBundle\Controller;

use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\TagModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ZenderWebhookController extends CommonApiController
{
    private const ANSWER_TAG = 'whatsapp_message_answered_zender';

    public function receiveAction(
        Request $request,
        IntegrationHelper $integrationHelper,
        LeadModel $leadModel,
        TagModel $tagModel,
        LoggerInterface $logger,
        $key,
        $phone = null,
        $message = null,
        $time = null,
        $datetime = null
    ) {
        $payload = $this->extractPayload($request, $key, $phone, $message, $time, $datetime);
        $expectedKey = $this->getExpectedWebhookKey($integrationHelper);

        if (empty($expectedKey) || !hash_equals((string) $expectedKey, (string) ($payload['key'] ?? ''))) {
            $logger->warning('[ZENDER][WEBHOOK] Unauthorized webhook request.', [
                'phone' => $payload['phone'] ?? null,
            ]);

            return new JsonResponse(['message' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (empty($payload['phone'])) {
            return new JsonResponse(['message' => 'Missing phone'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $lead = $this->findLeadByPhone($leadModel, (string) $payload['phone']);
        if (!$lead) {
            $logger->info('[ZENDER][WEBHOOK] Lead not found for incoming WhatsApp.', [
                'phone' => $payload['phone'],
            ]);

            return new JsonResponse(['message' => 'Lead not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $tag = $this->findOrCreateTag($tagModel);
        if ($tag && !$lead->getTags()->contains($tag)) {
            $lead->addTag($tag);
        }

        $leadModel->saveEntity($lead);

        $logger->info('[ZENDER][WEBHOOK] Incoming WhatsApp processed.', [
            'leadId'  => $lead->getId(),
            'phone'   => $payload['phone'],
            'message' => $payload['message'] ?? null,
        ]);

        return new JsonResponse([
            'message' => 'Webhook processed',
            'leadId'  => $lead->getId(),
        ], JsonResponse::HTTP_OK);
    }

    private function extractPayload(Request $request, $routeKey, $routePhone, $routeMessage, $routeTime, $routeDatetime)
    {
        $jsonPayload = [];
        $body = $request->getContent();
        if (is_string($body) && '' !== trim($body)) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $jsonPayload = $decoded;
            }
        }

        $phone = $routePhone
            ?? $request->get('phone')
            ?? $request->get('from')
            ?? ($jsonPayload['phone'] ?? null)
            ?? ($jsonPayload['from'] ?? null)
            ?? ($jsonPayload['sender'] ?? null);

        $message = $routeMessage
            ?? $request->get('message')
            ?? $request->get('text')
            ?? ($jsonPayload['message'] ?? null)
            ?? ($jsonPayload['text'] ?? null);

        return [
            'key'      => $routeKey ?? $request->get('key') ?? $request->get('secret') ?? ($jsonPayload['key'] ?? null) ?? ($jsonPayload['secret'] ?? null),
            'phone'    => is_string($phone) ? urldecode(trim($phone)) : null,
            'message'  => is_string($message) ? urldecode(trim($message)) : null,
            'time'     => $routeTime ?? $request->get('time') ?? ($jsonPayload['time'] ?? null),
            'datetime' => $routeDatetime ?? $request->get('datetime') ?? ($jsonPayload['datetime'] ?? null),
        ];
    }

    private function getExpectedWebhookKey(IntegrationHelper $integrationHelper)
    {
        $integration = $integrationHelper->getIntegrationObject('Zender');
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return null;
        }

        $keys = $integration->getDecryptedApiKeys();

        return $keys['zender_webhook_key']
            ?? $keys['webhook_secret']
            ?? $keys['zender_api_key']
            ?? null;
    }

    private function findLeadByPhone(LeadModel $leadModel, string $incomingPhone)
    {
        $leadRepository = $leadModel->getRepository();
        $candidates = $this->buildPhoneCandidates($incomingPhone);

        foreach ($candidates as $candidate) {
            $lead = $leadRepository->findOneBy(['phone' => $candidate]);
            if ($lead) {
                return $lead;
            }

            $lead = $leadRepository->findOneBy(['mobile' => $candidate]);
            if ($lead) {
                return $lead;
            }
        }

        return null;
    }

    private function buildPhoneCandidates(string $phone): array
    {
        $values = [];
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed);

        if ('' !== $trimmed) {
            $values[] = $trimmed;
            $values[] = ltrim($trimmed, '+');
            $values[] = '+'.ltrim($trimmed, '+');
        }

        if (is_string($digits) && '' !== $digits) {
            $values[] = $digits;
            $values[] = '+'.$digits;
        }

        return array_values(array_unique(array_filter($values)));
    }

    private function findOrCreateTag(TagModel $tagModel)
    {
        $tagRepository = $tagModel->getRepository();
        $tag = $tagRepository->findOneBy(['tag' => self::ANSWER_TAG]);

        if ($tag) {
            return $tag;
        }

        $tag = new Tag();
        $tag->setTag(self::ANSWER_TAG);
        $tagModel->saveEntity($tag);

        return $tag;
    }
}
