<?php

namespace App\CustomPageModel\Mapper;

use App\CommonCustomBase\Mapper\AbstractBaseMapper;
use App\Entity\Bloc\Comments;
use App\Entity\Bloc\ContentBloc;
use App\Entity\Bloc\CustomPageList;
use App\Entity\Bloc\Page;
use App\Entity\Bloc\Section;
use App\Entity\Bloc\SectionBloc;
use App\Entity\Bloc\Widget;
use App\Entity\Bloc\WidgetElement;
use App\Entity\General\Category;
use App\Entity\General\Tag;
use App\Entity\Remote\Security\User;
use App\Service\SessionContextEnum;
use App\Service\SessionContextService;

class ArticleMapper extends AbstractBaseMapper
{
    protected array $fields = [
        'm' => [
            'movieTitle' => '@self',
            'articleTitle' => 'contentBloc.title',
            'articleContent' => 'contentBloc.description',
            'iframes' => ['path' => 'widgetBloc.widgetElements', 'type' => WidgetElement::class],
            'seoDescription' => 'page.seoDescription',
            'seoTitle' => 'page.seoTitle'
        ],
        's' => [
            'thumbnail' => 'contentBloc.image.rawImageUrl',
            'categories' => ['path' => 'page.categories', 'type' => Category::class],
            'tags' => ['path' => 'page.tags', 'type' => Tag::class],
            'createdAt' => 'page.remoteCreationDate',
            'slug' => 'page.slug',
            'remoteID' => 'page.remoteId',
            'author' => 'page.author',
            'contributors' => ['path' => 'page.contributors', 'type' => User::class]
        ]
    ];

    const SEARCH_CONTENT = [
        'f_s_author_name' => 'authorName'
    ];

    public function afterSetThumbnail(): self
    {
        $this->page->getImage()->setRawImageUrl($this->getThumbnail());
        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->page->getAuthor()?->getFullName();
    }

    public function afterSetArticleTitle(): self
    {
        $this->page->setTitle($this->getArticleTitle());
        return $this;
    }

    public function getFirstSection(): Section
    {
        return $this->page->getVirtualBlocPages()->first()->getBloc();
    }

    public function getSecondSection(): Section
    {
        return $this->getFirstSection()->getBlocs()->first()->getBloc();
    }

    public function getContentBloc(): ContentBloc
    {
        return $this->getSecondSection()->getBlocs()->filter(fn(SectionBloc $sectionBloc) => $sectionBloc->getBloc()::class == ContentBloc::class)->first()->getBloc();
    }

    public function getWidgetBloc(): ?Widget
    {
        return ($sectionBloc = $this->getSecondSection()->getBlocs()->filter(fn(SectionBloc $sectionBloc) => $sectionBloc->getBloc()::class == Widget::class)->first()) ? $sectionBloc->getBloc() : null;
    }

    public function getCommentsBloc(): Comments
    {
        return $this->getSecondSection()->getBlocs()->filter(fn(SectionBloc $sectionBloc) => $sectionBloc->getBloc()::class == Comments::class)->first()->getBloc();
    }

    public function getOtherArticles(): ?CustomPageList
    {
        return ($bloc = $this->getFirstSection()->getBlocs()->filter(fn(SectionBloc $sectionBloc) => $sectionBloc->getBloc()::class == CustomPageList::class)->first()) ? $bloc->getBloc() : null;
    }
}