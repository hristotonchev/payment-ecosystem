<?php

namespace App\Controller;

use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payments')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    #[Route('/initiate', methods: ['POST'])]
    public function initiate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        foreach (['user_id', 'amount', 'currency', 'payment_method', 'customer_email'] as $field) {
            if (empty($data[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field "%s" is required.', $field));
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new UnprocessableEntityHttpException('Field "amount" must be a positive number.');
        }

        $transaction = $this->paymentService->initiate(
            userId:        $data['user_id'],
            amount:        (float) $data['amount'],
            currency:      $data['currency'],
            paymentMethod: $data['payment_method'],
            customerEmail: $data['customer_email'],
        );

        return $this->json([
            'transaction_id' => $transaction->getId(),
            'status'         => $transaction->getStatus()->value,
            'correlation_id' => $transaction->getCorrelationId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{transactionId}/confirm', methods: ['POST'])]
    public function confirm(string $transactionId): JsonResponse
    {
        $transaction = $this->paymentService->confirm($transactionId);

        return $this->json([
            'transaction_id' => $transaction->getId(),
            'status'         => $transaction->getStatus()->value,
        ]);
    }
}
