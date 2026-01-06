<?php

namespace App\CustomPageModel\CrudFields;

use App\CommonCustomBase\CrudFields\AbstractBaseFields;
use App\CommonCustomBase\CrudFields\CrudFieldsInterface;
use App\Field\CkeditorField;
use App\Field\CkeditorInlineField;
use App\Field\ElFinderImageType;
use App\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AuthorPageCrudFields extends AbstractBaseFields implements CrudFieldsInterface
{
    public function __invoke(): array
    {
        return [
            FormField::addTab("Informations de l'auteur")->setIcon('fa fa-user'),
            FormField::addPanel('Identité', 'fa fa-id-card')->addCssClass('w50'),
            TextField::new('mapper.user.firstName', 'Prénom')
                ->setColumns(6),
            TextField::new('mapper.user.lastName', 'Nom')
                ->setColumns(6),
            EmailField::new('mapper.user.email', 'Email')
                ->setColumns(12),
            TextField::new('mapper.user.mapper.pseudo', 'Pseudo')
                ->setColumns(12),

            FormField::addPanel('Présentation', 'fa fa-info-circle')->addCssClass('w50'),
            ElFinderImageType::new('mapper.user.rawImageUrl', 'Photo de profil')
                ->setColumns(12),
            CkeditorInlineField::new('mapper.user.title', 'Titre / Fonction')
                ->setColumns(12),
            CkeditorField::new('mapper.user.mapper.bio', 'Biographie')
                ->setColumns(12),
            TextField::new('mapper.user.mapper.role', 'Rôle')
                ->setColumns(6),
            CkeditorField::new('mapper.user.mapper.quote', 'Citation')
                ->setColumns(12),


            FormField::addPanel('Réseaux sociaux', 'fa fa-share-alt')->addCssClass('w50'),
            TextField::new('mapper.user.mapper.twitter', 'Twitter')
                ->setColumns(12),
            TextField::new('mapper.user.mapper.instagram', 'Instagram')
                ->setColumns(12),
            TextField::new('mapper.user.mapper.linkedin', 'LinkedIn')
                ->setColumns(12),
        ];
    }
}
