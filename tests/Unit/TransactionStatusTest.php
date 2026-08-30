<?php

declare(strict_types=1);

namespace CodeConjure\SimplePay\Tests\Unit;

use CodeConjure\SimplePay\Exception\UnexpectedResponseException;
use CodeConjure\SimplePay\TransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionStatus::class)]
final class TransactionStatusTest extends TestCase
{
    public function testFinishedIsTheOnlySuccessfulStatus(): void
    {
        foreach (TransactionStatus::cases() as $status) {
            self::assertSame(
                TransactionStatus::Finished === $status,
                $status->isSuccessful(),
                sprintf('%s sikeressége', $status->value),
            );
        }
    }

    /** @return iterable<string, array{TransactionStatus, bool}> */
    public static function finality(): iterable
    {
        yield 'INIT' => [TransactionStatus::Init, false];
        yield 'INPAYMENT' => [TransactionStatus::InPayment, false];
        yield 'AUTHORIZED' => [TransactionStatus::Authorized, false];
        yield 'INFRAUD' => [TransactionStatus::InFraud, false];
        yield 'FINISHED' => [TransactionStatus::Finished, true];
        yield 'CANCELLED' => [TransactionStatus::Cancelled, true];
        yield 'TIMEOUT' => [TransactionStatus::Timeout, true];
        yield 'NOTAUTHORIZED' => [TransactionStatus::NotAuthorized, true];
        yield 'FRAUD' => [TransactionStatus::Fraud, true];
        yield 'REVERSED' => [TransactionStatus::Reversed, true];
        yield 'REFUND' => [TransactionStatus::Refund, true];
    }

    #[DataProvider('finality')]
    public function testFinality(TransactionStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->isFinal());
    }

    public function testFinalityProviderCoversAllCases(): void
    {
        $providedCases = array_map(
            static fn (array $data) => $data[0],
            iterator_to_array(self::finality()),
        );

        $allCases = TransactionStatus::cases();

        $missingCases = array_diff_key(
            array_column($allCases, 'name', 'name'),
            array_column($providedCases, 'name', 'name'),
        );

        self::assertEmpty(
            $missingCases,
            sprintf('Finality data provider is missing cases: %s', implode(', ', $missingCases)),
        );
    }

    public function testFinishedParsesFromTheApiValue(): void
    {
        self::assertSame(TransactionStatus::Finished, TransactionStatus::fromApi('FINISHED'));
    }

    public function testAnUnknownStatusIsLoudNotSilent(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('COMPLETE');

        TransactionStatus::fromApi('COMPLETE');
    }

    public function testTheExceptionListsTheKnownStatuses(): void
    {
        try {
            TransactionStatus::fromApi('WAITING');
            self::fail('Kivételt vártunk.');
        } catch (UnexpectedResponseException $exception) {
            self::assertStringContainsString('FINISHED', $exception->getMessage());
        }
    }
}
