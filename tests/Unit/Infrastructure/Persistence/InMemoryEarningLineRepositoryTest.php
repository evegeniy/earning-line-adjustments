<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Infrastructure\Persistence;

use DateTimeImmutable;
use Payroll\Domain\EarningLine\AdjustmentComment;
use Payroll\Domain\EarningLine\EarningLine;
use Payroll\Domain\EarningLine\EarningLineId;
use Payroll\Domain\EarningLine\SpecialistId;
use Payroll\Domain\Shared\ConcurrencyConflict;
use Payroll\Domain\Shared\Currency;
use Payroll\Domain\Shared\Money;
use Payroll\Infrastructure\Persistence\InMemoryEarningLineRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryEarningLineRepositoryTest extends TestCase
{
    private InMemoryEarningLineRepository $repository;

    private EarningLineId $lineId;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEarningLineRepository();
        $this->lineId = new EarningLineId('line-42');
    }

    public function testReturnsNullForUnknownLine(): void
    {
        self::assertNull($this->repository->find($this->lineId));
    }

    public function testEveryFindReturnsAnIndependentInstance(): void
    {
        $this->repository->save($this->newLine('1000.00'));

        $unsavedCopy = $this->find();
        $this->adjust($unsavedCopy, '-45.55');

        self::assertNotSame($unsavedCopy, $this->find());
        self::assertFalse($this->find()->isManuallyAdjusted());
    }

    public function testDoesNotPersistPendingEvents(): void
    {
        $this->repository->save($this->newLine('1000.00'));

        self::assertSame([], $this->find()->releaseEvents());
    }

    public function testSameInstanceCanBeSavedRepeatedly(): void
    {
        $line = $this->newLine('1000.00');
        $this->repository->save($line);

        $this->adjust($line, '-45.55');
        $this->repository->save($line);

        self::assertEquals(Money::of('954.45', Currency::USD), $this->find()->currentValue());
    }

    public function testStaleRecalculationCannotOverwriteFirstManualAdjustment(): void
    {
        $this->repository->save($this->newLine('1050.00'));
        $loadedByRecalculationJob = $this->find();
        $loadedBySpecialist = $this->find();

        $this->adjust($loadedBySpecialist, '-45.55');
        $this->repository->save($loadedBySpecialist);

        $loadedByRecalculationJob->recalculate(Money::of('1200.00', Currency::USD), new DateTimeImmutable());

        try {
            $this->repository->save($loadedByRecalculationJob);
            self::fail('A stale instance must not be saved.');
        } catch (ConcurrencyConflict) {
        }

        $stored = $this->find();
        self::assertEquals(Money::of('1050.00', Currency::USD), $stored->systemValue());
        self::assertEquals(Money::of('1004.45', Currency::USD), $stored->currentValue());
    }

    public function testIgnoredRecalculationDoesNotMakeConcurrentAdjustmentFail(): void
    {
        $line = $this->newLine('1050.00');
        $this->adjust($line, '-45.55');
        $this->repository->save($line);
        $loadedByRecalculationJob = $this->find();
        $loadedBySpecialist = $this->find();

        $loadedByRecalculationJob->recalculate(Money::of('1200.00', Currency::USD), new DateTimeImmutable());
        $this->repository->save($loadedByRecalculationJob);

        $this->adjust($loadedBySpecialist, '+100.10');
        $this->repository->save($loadedBySpecialist);

        self::assertEquals(Money::of('1104.55', Currency::USD), $this->find()->currentValue());
    }

    public function testSameLineCannotBeCreatedTwice(): void
    {
        $this->repository->save($this->newLine('1000.00'));

        $this->expectException(ConcurrencyConflict::class);

        $this->repository->save($this->newLine('2000.00'));
    }

    private function newLine(string $systemValue): EarningLine
    {
        return EarningLine::calculate($this->lineId, Money::of($systemValue, Currency::USD), new DateTimeImmutable());
    }

    private function find(): EarningLine
    {
        $line = $this->repository->find($this->lineId);
        self::assertNotNull($line);

        return $line;
    }

    private function adjust(EarningLine $line, string $amount): void
    {
        $line->addManualAdjustment(
            Money::of($amount, Currency::USD),
            new AdjustmentComment('Some reason'),
            new SpecialistId('specialist-1'),
            new DateTimeImmutable(),
        );
    }
}
