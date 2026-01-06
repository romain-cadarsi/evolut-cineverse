<?php

namespace App\CustomPageModel\CrudFields;

use App\CommonCustomBase\CrudFields\AbstractBaseFields;
use App\CommonCustomBase\CrudFields\CrudFieldsInterface;
use App\CustomPageModel\Mapper\UserMapper;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class UserCrudFields extends AbstractBaseFields implements CrudFieldsInterface
{
    public function __invoke(): array
    {
        return [
            FormField::addPanel('Informations supplémentaires', "fa fa-info")->addCssClass("w50"),
            TextField::new('mapper.pseudo', 'Pseudo')->setColumns(12),
            TextField::new('mapper.role', 'Rôle')->setColumns(12),
            TextField::new('mapper.quote', 'Citation')->setColumns(12),
            TextareaField::new('mapper.bio', 'Biographie')->setColumns(12),

            FormField::addPanel('Réseaux sociaux', "fa fa-share-nodes")->addCssClass("w50"),
            TextField::new('mapper.twitter', 'Twitter')->setColumns(12),
            TextField::new('mapper.instagram', 'Instagram')->setColumns(12),
            TextField::new('mapper.linkedin', 'LinkedIn')->setColumns(12),
        ];
    }
}