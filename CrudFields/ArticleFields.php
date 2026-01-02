<?php


namespace App\CustomPageModel\CrudFields;

use App\CommonCustomBase\CrudFields\AbstractBaseFields;
use App\CommonCustomBase\CrudFields\CrudFieldsInterface;
use App\Entity\Bloc\WidgetElement;
use App\Entity\General\Category;
use App\Entity\General\Tag;
use App\Field\AuthorField;
use App\Field\CkeditorField;
use App\Field\CkeditorInlineField;
use App\Field\ContributorsField;
use App\Field\ElFinderImageType;
use App\Field\FormField;
use App\Field\MultipleEntitiesField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ArticleFields extends AbstractBaseFields implements CrudFieldsInterface
{

    public function __invoke(): array
    {
        return [
            FormField::addTab("sourceDataTab"),
            FormField::addPanel("globalDataPanel", "Données principales")->addCssClass("w50"),
            CkeditorField::new("mapper.articleTitle", 'Titre de l\'article')
                ->setColumns('col-md-12 col-lg-6 col-xl-4'),
            TextField::new('mapper.movieTitle', 'Titre du film')
                ->setColumns('col-md-12 col-lg-6 col-xl-4'),
            ElFinderImageType::new('mapper.thumbnail', 'Image')->setColumns('col-md-12 col-lg-12 col-xl-4'),
            MultipleEntitiesField::new('mapper.categories', "Catégories associées")
                ->setColumns('col-md-12 col-lg-6 col-xl-6')
                ->configureClass(Category::class)
                ->onlyOnForms(),
            MultipleEntitiesField::new('mapper.tags', "Tags associés")
                ->setColumns('col-md-12 col-lg-6 col-xl-6')
                ->configureClass(Tag::class)
                ->onlyOnForms(),


            CkeditorField::new("mapper.articleContent", 'Contenu')
                ->onlyOnForms(),
//
            FormField::addPanel("metaDataPanel", "Metas")->addCssClass("w30"),
            AuthorField::new('mapper.author', 'Auteur')
                ->setPermission('CAN_PUBLISH_CPM'),
            ContributorsField::new('mapper.contributors', 'Contributeurs')
                ->setPermission('CAN_PUBLISH_CPM'),

            $this->generateCollectionField('mapper.iframes', "Iframes", WidgetElement::class),

            FormField::addPanel("SEOPanel", "Seo")->addCssClass("20"),
            CkeditorInlineField::new('mapper.seoTitle', 'Titre Seo'),
            CkeditorField::new('mapper.seoDescription', 'Description Seo'),
//            IntegerField::new('mapper.viewCount','Nombre de vues')->setColumns(12),
            DateField::new('remoteCreationDate', 'Date de création')->setColumns(12),

        ];
    }
}