<?php

namespace App\CustomPageModel\Importer;

use App\CommonCustomBase\Importer\AbstractImporter;
use App\Entity\Remote\Security\Domain;
use App\Entity\Remote\Security\User;
use App\Service\EnvService;
use Doctrine\Common\Collections\ArrayCollection;

class UserImporter extends AbstractImporter
{
    protected $importUsersUrl = "https://cineverse.fr/export_users.php";
    protected $importSingleUserUrl = "https://cineverse.fr/export_users.php?id="; // Base URL for single user import

    private $USER_ROLES_MAPPING = [
        'author' => 'ROLE_AUTHOR',
        'contributor' => 'ROLE_CONTRIBUTOR',
        'editor' => 'ROLE_EDITOR',
        'administrator' => 'ROLE_ADMINISTRATOR'
    ];

    /**
     * Import all users at once
     *
     * @param bool $sequential
     * @return bool Success status
     */
    function importAll(bool $sequential = false): bool
    {
        $output = $this->getOutput();

        $output?->title("Starting User import");
        $data = json_decode(file_get_contents($this->importUsersUrl), true);

        $domain = $this->getDomain();

        foreach ($data as $userData) {
            $this->processUserData($userData, $domain);
        }

        $this->remoteEntityManager->flush();
        $output?->title("User import finished");

        return true;
    }

    /**
     * Import a single user by ID
     *
     * @param mixed $id User ID to import
     * @return bool Success status
     */
    public function import(mixed $id): bool
    {
        $output = $this->getOutput();
        $output?->title("Starting single user import for ID: $id");

        $url = $this->importSingleUserUrl . $id;
        $userData = json_decode(file_get_contents($url), true);

        if (empty($userData) || isset($userData['error'])) {
            $output?->error("Failed to import user with ID: $id");
            return false;
        }

        $domain = $this->getDomain();
        $this->processUserData($userData, $domain);

        $this->remoteEntityManager->flush();
        $output?->title("Single user import finished for ID: $id");

        return true;
    }

    /**
     * Get or create the domain entity
     *
     * @return Domain The domain entity
     */
    private function getDomain(): Domain
    {
        $domainName = EnvService::get_env('DOMAIN_NAME');
        $domain = $this->remoteEntityManager->getRepository(Domain::class)->findOneBy(['name' => $domainName]);

        if (!$domain) {
            $domain = new Domain();
        }

        $domain->setTmp(false)
            ->setName($domainName);

        $this->remoteEntityManager->persist($domain);

        return $domain;
    }

    /**
     * Process user data and create/update user entity
     *
     * @param array $userData User data from API
     * @param Domain $domain Domain entity
     * @return User|null Created/updated user or null if skipped
     */
    private function processUserData(array $userData, Domain $domain): ?User
    {
        if ($userData['email'] == 'cadarsir@gmail.com') {
            return null; // Skip this user
        }

        $userId = $userData['id'];
        $image = null;

        // Process image if available
        if (!empty($imageUrl = $userData['image'])) {
            $imageInfo = $this->imageImporterService->importImage($imageUrl, 'users', null, "$userId.png");
            if ($imageInfo['status'] == 'success') {
                $image = $imageInfo['relativePath'];
            }
        }

        // Find existing user or create new one
        $entity = $this->remoteEntityManager->getRepository(User::class)->findOneBy(['email' => $userData['email']]);
        if (!$entity) {
            $entity = new User();
        }

        // Update domains collection
        $domains = array_merge($entity->getDomains(), [$domain]);
        $domains = array_unique($domains);
        $domains = new ArrayCollection($domains);

        $userData['lastName'] = str_replace($userData['firstName'], '', $userData['fullName']);
        // Update user properties
        $entity->setPseudo($userData['pseudo'])
            ->setFirstName(trim($userData['firstName']))
            ->setLastName(trim($userData['lastName'] ?? ""))
            ->setEmail(trim($userData['email']))
            ->setRawImageUrl($image)
            ->setRemoteId($userData['id'])
            ->setTmp(false)
            ->setDomains($domains)
            ->setRemoteCreationDate(new \DateTime($userData['remote_created_at']))
            ->setRemoteOrigin('cineverse.fr')
            ->setOriginDomain($this->remoteEntityManager->getRepository(Domain::class)->findOneBy(['name' => 'cineverse.cadarsir.fr']))
            ->setCustomPageModelUuids(['762ea469-90a5-43cb-a38c-918eae2d3eb6'])
            ->setRoles([$this->USER_ROLES_MAPPING[$userData['role']] ?? null]);

        $this->remoteEntityManager->persist($entity);

        return $entity;
    }

    function importBatch(int $batchId): bool
    {
        return true;
    }
}