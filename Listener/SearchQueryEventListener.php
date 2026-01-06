<?php

namespace App\CustomPageModel\Listener;

use App\CustomPageModel\Mapper\AuthorPageMapper;
use App\CustomPageModel\Mapper\UserMapper;
use App\CustomPageModel\Service\CineverseService;
use App\Entity\Bloc\CustomPage;
use App\Event\SearchQueryEvent;
use App\Service\RequestService;
use App\Service\SessionContextEnum;
use App\Service\SessionContextService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: SearchQueryEvent::class, priority: 30)]
class SearchQueryEventListener
{
    public function __construct(private CineverseService $service)
    {

    }

    public function __invoke(SearchQueryEvent $event): void
    {
        $searchBuilder = $event->getSearchBuilder();
        $step = $event->getContext()['step'] ?? null;
        $class = $searchBuilder->class;
        $activePage = SessionContextService::getContext(SessionContextEnum::LOADED_PAGE);

        if (CineverseService::function_get_author() && $class == CustomPage::class && $activePage instanceof CustomPage && $activePage->getCustomPageModel()->getId() == UserMapper::CPM_ID) {
            $author = $this->service->function_get_author();
            $searchBuilder->addFilter("author_name:=`$author`");
//            dump($searchBuilder, $step);
        }

        $customPageList = $searchBuilder->getCustomPageList();
        $customPageId = RequestService::createFromGlobals()->query->get('customPageId');
//        dump($customPageList, $customPageId);
        if ($customPageList->getName() == 'Articles auteur' && $customPageId) {
            $customPage = SessionContextService::getRepositoryFor(CustomPage::class)->find($customPageId);
            if ($customPage) {
                $authorName = $customPage->getMapper()->getUser()->getFullName();
                $searchBuilder->addFilter("author:=`$authorName`");
            }
        }
    }
}