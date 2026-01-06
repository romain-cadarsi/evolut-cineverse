<?php

namespace App\CustomPageModel\Mapper;

use App\CommonCustomBase\Mapper\AbstractBaseMapper;
use App\Entity\Bloc\Bloc;
use App\Entity\Bloc\CustomPageList;
use App\Entity\Remote\Security\User;
use App\Service\SessionContextService;

class AuthorPageMapper extends AbstractBaseMapper
{
    const CPM_ID = 4;

    protected array $fields = [
        'm' => [],
        's' => []
    ];

    /**
     * Récupère l'utilisateur lié à cette page d'auteur via remoteUuid
     */
    public function getUser(): ?User
    {
        return SessionContextService::getRepositoryFor(User::class)
            ->findOneBy(['uuid' => $this->entity->getRemoteUuid()]);
    }

    public function afterMap(): AbstractBaseMapper
    {
        parent::afterMap();

        $this->entity->getImage()->setRawImageUrl($this->getUser()->getImageUrl());
        /** @var ?CustomPageList $customPageList */
        $customPageList = array_values(array_filter($this->entity->getAllBlocs(), fn(Bloc $bloc) => $bloc instanceof CustomPageList))[0] ?? null;
        if ($customPageList) {
            $customPageList->setIncludedFacets([
                'author:' . $this->getUser()->getFullName()
            ]);
        }

        return $this;
    }
}
