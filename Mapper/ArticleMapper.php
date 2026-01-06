<?php

namespace App\CustomPageModel\Mapper;

use App\CommonCustomBase\Mapper\AbstractBaseMapper;
use App\Entity\General\Category;
use App\Entity\General\Tag;
use App\Entity\Remote\Security\User;
use App\Service\Cache\CacheManager;

class ArticleMapper extends AbstractBaseMapper
{

    const CPM_ID = 1;

    protected array $fields = [
        'm' => [
            'movieTitle' => '@self',
            'articleTitle' => '@self',
            'articleContent' => '@self',
            'videos' => '@self',
            'podcasts' => '@self',
            'seoDescription' => 'page.seoDescription',
            'seoTitle' => 'page.seoTitle',
            'shortDescription' => '@self'
        ],
        's' => [
            'thumbnail' => '@self',
            'categories' => ['path' => 'page.categories', 'type' => Category::class],
            'tags' => ['path' => 'page.tags', 'type' => Tag::class],
            'createdAt' => 'page.remoteCreationDate',
            'slug' => 'page.slug',
            'remoteID' => 'page.remoteId',
            'author' => 'page.author',
            'genres' => '@self',
            'contributors' => ['path' => 'page.contributors', 'type' => User::class]
        ]
    ];

    const SEARCH_CONTENT = [
        'f_s_author_name' => 'authorName',
        'f_a_genres' => 'genres'
    ];

    public function afterSetThumbnail(): self
    {
        $this->entity->getImage()->setRawImageUrl($this->getThumbnail());
        return $this;
    }

    public function getFinalShortDescription(?string $shortDescription): ?string
    {
        if (empty($shortDescription)) {
            $seoDescription = $this->getSeoDescription();
            if (!empty($seoDescription)) {
                return $seoDescription;
            }
        }
        return $shortDescription;

    }

    public function getAuthorName(): ?string
    {
        return $this->entity->getAuthor()?->getFullName();
    }

    public function afterSetArticleTitle(): self
    {
        $this->entity->setTitle($this->getArticleTitle());
        return $this;
    }

    public function getTempsLecture(): int
    {
        return CacheManager::getValue(
            'blog_article_reading_time_' . $this->entity->getId(),
            function () {
                $contenu = $this->getArticleContent() ?? '';

                // Compte le nombre de mots (strip HTML tags first)
                $textOnly = strip_tags($contenu);
                $wordCount = str_word_count($textOnly);

                // Calcul : ~200 mots par minute
                $minutes = max(1, round($wordCount / 200));

                return (int)$minutes;
            },
            $this->entity->getCacheTags()
        );
    }
}