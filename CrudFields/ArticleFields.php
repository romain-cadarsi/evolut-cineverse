<?php


namespace App\CustomPageModel\CrudFields;

use App\CommonCustomBase\CrudFields\AbstractBaseFields;
use App\CommonCustomBase\CrudFields\CrudFieldsInterface;
use App\Entity\General\Category;
use App\Entity\General\Tag;
use App\Field\AuthorField;
use App\Field\CkeditorField;
use App\Field\CkeditorInlineField;
use App\Field\ContributorsField;
use App\Field\ElFinderImageType;
use App\Field\FormField;
use App\Field\MultipleEntitiesField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ArticleFields extends AbstractBaseFields implements CrudFieldsInterface
{

    public function __invoke(): array
    {
        return [
            FormField::addTab("Contenu")->setIcon('fa fa-file-text'),
            FormField::addPanel('Informations principales', 'fa fa-info-circle')->addCssClass('w60'),
            CkeditorField::new("mapper.articleTitle", 'Titre de l\'article')
                ->setColumns(12),
            TextField::new('mapper.movieTitle', 'Titre du film')
                ->setColumns(6),
            ElFinderImageType::new('mapper.thumbnail', 'Image de couverture')
                ->setColumns(6),

            FormField::addPanel('Classification', 'fa fa-tags')->addCssClass('w40'),
            MultipleEntitiesField::new('mapper.categories', "Catégories")
                ->setColumns(12)
                ->configureClass(Category::class)
                ->onlyOnForms(),
            MultipleEntitiesField::new('mapper.tags', "Tags")
                ->setColumns(12)
                ->configureClass(Tag::class)
                ->onlyOnForms(),

            FormField::addPanel('Corps de l\'article', 'fa fa-align-left')->addCssClass('w100'),
            CkeditorField::new("mapper.articleContent", 'Contenu riche')
                ->setColumns(12)
                ->onlyOnForms(),

            FormField::addTab("Médias & Liens")->setIcon('fa fa-play-circle'),
            FormField::addPanel('Multimédia', 'fa fa-share-alt')->addCssClass('w50'),
            CollectionField::new('mapper.videos', 'Vidéos (Embed codes)')
                ->setColumns(12)
                ->setEntryType(TextType::class)
                ->allowAdd()
                ->allowDelete(),
            CollectionField::new('mapper.podcasts', 'Podcasts (Embed codes)')
                ->setColumns(12)
                ->setEntryType(TextType::class)
                ->allowAdd()
                ->allowDelete(),

            FormField::addPanel('Paramètres de publication', 'fa fa-user-edit')->addCssClass('w50'),
            AuthorField::new('mapper.author', 'Auteur principal')
                ->setColumns(12)
                ->setPermission('CAN_PUBLISH_CPM'),
            ContributorsField::new('mapper.contributors', 'Contributeurs')
                ->setColumns(12)
                ->setPermission('CAN_PUBLISH_CPM'),
            DateField::new('remoteCreationDate', 'Date de publication originale')
                ->setColumns(12),

            FormField::addTab("SEO")->setIcon('fa fa-search'),
            FormField::addPanel('Optimisation Référencement', 'fa fa-rocket')->addCssClass('w100'),
            CkeditorInlineField::new('mapper.seoTitle', 'Titre SEO (Meta Title)')
                ->setColumns(12),
            CkeditorField::new('mapper.seoDescription', 'Description SEO (Meta Description)')
                ->setColumns(12),
        ];
    }
}