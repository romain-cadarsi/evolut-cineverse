<?php

namespace App\CustomPageModel\Mapper;

use App\CommonCustomBase\Mapper\AbstractBaseMapper;
use App\Entity\Bloc\CustomPage;
use App\Service\Cache\CacheManager;
use App\Service\Search\SearchBuilder;
use App\Service\SessionContextEnum;
use App\Service\SessionContextService;

class UserMapper extends AbstractBaseMapper
{

    const CPM_ID = 4;

    protected array $fields = [
        'm' => [  // Multilingues (varient par langue)
            'bio' => '@self',
            'role' => '@self',
            'quote' => '@self',
        ],
        's' => [  // Shared (indépendants de la langue)
            'pseudo' => '@self',
            'twitter' => '@self',
            'instagram' => '@self',
            'linkedin' => '@self'
        ]
    ];

    /**
     * Compte tous les articles de l'auteur
     */
    public function getNbArticles(): int
    {
        return CacheManager::getValue(
            'user_nb_articles_' . $this->entity->getUuid(),
            function () {
                $searchService = SessionContextService::getContext(SessionContextEnum::SEARCH_SERVICE);
                $search = SearchBuilder::create(CustomPage::class);
                $search->q = '*';
                $search->addFilter("author_name:=`{$this->entity->getFullName()}`");
                $search->includeFields = ['id'];

                $results = $searchService->executeSearch($search, 'countArticles');
                return $results['found'] ?? 0;
            },
            ['user_' . $this->entity->getUuid()]
        );
    }

    /**
     * Compte les interviews de l'auteur
     */
    public function getNbInterviews(): int
    {
        return CacheManager::getValue(
            'user_nb_interviews_' . $this->entity->getUuid(),
            function () {
                $searchService = SessionContextService::getContext(SessionContextEnum::SEARCH_SERVICE);
                $search = SearchBuilder::create(CustomPage::class);
                $search->q = 'interview';
                $search->count = 0;
                $search->includeFields = ['id'];

                $results = $searchService->executeSearch($search, 'countInterviews');
                return $results['found'] ?? 0;
            },
            ['user_' . $this->entity->getUuid()]
        );
    }

    /**
     * Compte les podcasts de l'auteur
     */
    public function getNbPodcasts(): int
    {
        return CacheManager::getValue(
            'user_nb_podcasts_' . $this->entity->getUuid(),
            function () {
                $searchService = SessionContextService::getContext(SessionContextEnum::SEARCH_SERVICE);
                $search = SearchBuilder::create(CustomPage::class);
                $search->q = 'podcast';
                $search->count = 0;
                $search->includeFields = ['id'];
                $results = $searchService->executeSearch($search, 'countPodcasts');
                return $results['found'] ?? 0;
            },
            ['user_' . $this->entity->getUuid()]
        );
    }

    /**
     * Retourne les années d'expérience (valeur stockée ou calculée depuis createdAt)
     */
    public function getYearsExp(): int
    {
        return CacheManager::getValue(
            'user_years_exp_' . $this->entity->getUuid(),
            function () {
                // Sinon, on calcule depuis la date de création
                $createdAt = $this->entity->getCreatedAt();
                if ($createdAt) {
                    $now = new \DateTime();
                    $diff = $now->diff($createdAt);
                    return max(0, $diff->y);
                }

                return 0;
            },
            ['user_' . $this->entity->getUuid()]
        );
    }

    public function getCustomPage(): ?CustomPage
    {
        return SessionContextService::getRepositoryFor(CustomPage::class)->findOneBy([
            'remoteUuid' => $this->entity->getUuid()
        ]);
    }

    public function getFinalBio(?string $bio): ?string
    {
        return empty($bio) ? $this->entity->getDescription() : $bio;
    }
}