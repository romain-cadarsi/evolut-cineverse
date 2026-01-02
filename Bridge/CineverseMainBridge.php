<?php

namespace App\CustomPageModel\Bridge;

use App\CommonCustomBase\Bridge\AbstractMainBridge;
use App\CommonCustomBase\CrudFields\ErrorPageFields;
use App\CommonCustomBase\CrudFields\FormulaireFields;
use App\CommonCustomBase\Mapper\ErrorPageMapper;
use App\CommonCustomBase\Mapper\FormulaireMapper;
use App\CustomPageModel\CrudFields\ArticleFields;
use App\CustomPageModel\Importer\ArticleImporter;
use App\CustomPageModel\Importer\UserImporter;
use App\CustomPageModel\Mapper\ArticleMapper;
use App\Service\VersionService;
use Doctrine\ORM\EntityManagerInterface;

class CineverseMainBridge extends AbstractMainBridge
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected EntityManagerInterface $remoteEntityManager,
        protected VersionService         $versionService,
        protected ArticleImporter        $articleImporter,
        protected UserImporter           $userImporter,
    )
    {
    }

    public function getMappers(): array
    {
        return [
            1 => ArticleMapper::class,
            2 => ErrorPageMapper::class,
            3 => FormulaireMapper::class
        ];
    }

    public function getImporters(): array
    {
        return [
            1 => $this->articleImporter,
            "user" => $this->userImporter
        ];
    }

    public function getCrudFields(): array
    {
        return [
            1 => ArticleFields::class,
            2 => ErrorPageFields::class,
            3 => FormulaireFields::class
        ];
    }

    public function getPageFields(): array
    {
        return [];
    }

    public function getPageMappers(): array
    {
        return [];
    }

}