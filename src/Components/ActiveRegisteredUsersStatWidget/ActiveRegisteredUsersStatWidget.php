<?php

namespace Crm\UsersModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;

/**
 * This widget renders simple single stat widget with total active (not deleted) users count.
 *
 * @package Crm\UsersModule\Components
 */
class ActiveRegisteredUsersStatWidget extends BaseLazyWidget
{
    private $templateName = 'active_registered_users_stat_widget.latte';

    const SEGMENT_CODE = 'active_registered_users';

    private $segmentsRepository;

    private $segmentsValuesRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository
    ) {
        parent::__construct($lazyWidgetManager);

        $this->segmentsRepository = $segmentsRepository;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
    }

    public function identifier()
    {
        return 'activeregisteredusersstatwidget';
    }

    public function render()
    {
        if ($this->segmentsRepository->exists(self::SEGMENT_CODE)) {
            $this->template->totalPaidSubscribersLink = $this->presenter->link(
                ':Segment:StoredSegments:show',
                $this->segmentsRepository->findByCode(self::SEGMENT_CODE)->id
            );
        } else {
            throw new \Exception('Trying to render ActiveRegisteredUsersStatWidget with non-existing segment: ' . self::SEGMENT_CODE . '. Did you need to run application:seed command?');
        }

        $count = $this->segmentsValuesRepository->valuesBySegmentCode(self::SEGMENT_CODE)
            ->order('date DESC')
            ->limit(1)
            ->select('*')
            ->fetch();

        $this->template->wasCalculated = $count ? true : false;
        $this->template->totalUsers = $count ? $count->value : 0;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
