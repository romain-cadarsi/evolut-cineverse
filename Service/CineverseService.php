<?php

namespace App\CustomPageModel\Service;

use App\CustomPageModel\Mapper\ArticleMapper;
use App\CustomPageModel\Mapper\UserMapper;
use App\Entity\Bloc\CustomPage;
use App\Entity\Remote\Security\User;
use App\Service\RequestService;
use App\Service\Search\SearchBuilder;
use App\Service\Search\SearchService;
use App\Service\SessionContextEnum;
use App\Service\SessionContextService;
use App\Twig\Traits\TwigServiceExtensionTrait;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;

class CineverseService extends AbstractExtension
{
    use TwigServiceExtensionTrait;

    public function __construct(
        private KernelInterface       $kernel,
        private UrlGeneratorInterface $urlGenerator,
        private MessageBusInterface   $bus
    )
    {
    }

    public function function_get_featured_elements(): array
    {
        return SessionContextService::getRepositoryFor(CustomPage::class)->findBy([
            'customPageModel' => ArticleMapper::CPM_ID,
            'hidden' => false,
            'tmp' => false
        ], null, 3);
    }

    public function function_get_article(): CustomPage
    {
        $activePage = SessionContextService::getContext(SessionContextEnum::LOADED_PAGE);
        if ($activePage instanceof CustomPage && $activePage->getCustomPageModel()->getId() == ArticleMapper::CPM_ID) {
            return $activePage;
        }
        return SessionContextService::getRepositoryFor(CustomPage::class)->findOneBy([
            'customPageModel' => ArticleMapper::CPM_ID,
            'hidden' => false,
            'tmp' => false
        ]);
    }

    public static function function_get_author(): ?User
    {

        $activePage = SessionContextService::getContext(SessionContextEnum::LOADED_PAGE);

        if ($activePage instanceof CustomPage && $activePage->getCustomPageModel()->getId() == UserMapper::CPM_ID) {
            $user = SessionContextService::getRepositoryFor(User::class)->findOneBy([
                'uuid' => $activePage->getRemoteUuid()
            ]);
            return $user;
        }

        $authorName = (RequestService::createFromGlobals())->query->get('author');
        if ($authorName) {
            /** @var SearchService $searchService */
            $searchService = SessionContextService::getContext(SessionContextEnum::SEARCH_SERVICE);
            $search = SearchBuilder::create(User::class);
            $search->q = $authorName;
            $search->count = 1;
            $results = $searchService->executeSearch($search, 'authorSearch');

            return $results['items'][0] ?? null;
        }
//        if ($activePage instanceof CustomPage && $activePage->getCustomPageModel()->getId() == ArticleMapper::CPM_ID) {
//            return $activePage;
//        }
        return SessionContextService::getRepositoryFor(User::class)->findOneBy([
            'verified' => true,
        ]);
    }
}
