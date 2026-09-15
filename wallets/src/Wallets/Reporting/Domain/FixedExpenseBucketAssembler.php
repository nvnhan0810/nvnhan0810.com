<?php

namespace Wallets\Reporting\Domain;

use DateTimeImmutable;

/**
 * Gom occurrence vào từng bucket theo due_date.
 */
final class FixedExpenseBucketAssembler
{
    /**
     * @param  list<array{index:int,key:string,label:string,start:string,end:string}>  $buckets
     * @param  list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>  $events
     * @return list<array{index:int,key:string,label:string,start:string,end:string,total:float,items:list<array{source:string,source_id:int|string,name:string,amount:float,due_date:string,type_label:string}>}>
     */
    public function assemble(array $buckets, array $events): array
    {
        $result = [];

        foreach ($buckets as $bucket) {
            $start = new DateTimeImmutable($bucket['start']);
            $end = new DateTimeImmutable($bucket['end']);
            $items = array_values(array_filter(
                $events,
                static function (array $event) use ($start, $end): bool {
                    $due = new DateTimeImmutable($event['due_date']);

                    return $due >= $start && $due <= $end;
                }
            ));
            $total = array_reduce(
                $items,
                static fn (float $carry, array $item): float => $carry + (float) $item['amount'],
                0.0,
            );

            $result[] = [
                'index' => $bucket['index'],
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'start' => $bucket['start'],
                'end' => $bucket['end'],
                'total' => $total,
                'items' => $items,
            ];
        }

        return $result;
    }
}
