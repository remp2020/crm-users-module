<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Seeders\SegmentsTrait;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
    use SegmentsTrait;

    private $segmentGroupsRepository;

    private $segmentsRepository;

    public function __construct(
        SegmentGroupsRepository $segmentGroupsRepository,
        SegmentsRepository $segmentsRepository,
    ) {
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->seedSegment(
            $output,
            'Active users',
            'active_registered_users',
            <<<SQL
SELECT %fields%
FROM %table%
WHERE
    %where%
    AND `active` = 1
    AND deleted_at IS NULL
GROUP BY %table%.id
SQL,
        );
    }
}
