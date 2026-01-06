<?php

declare(strict_types=1);

namespace App\CustomPageModel\Command;

use App\CustomPageModel\Mapper\UserMapper;
use App\Entity\Bloc\CustomPage;
use App\Entity\Bloc\CustomPageModel;
use App\Entity\Remote\Security\User;
use App\Service\EnvService;
use App\Service\SessionContextService;
use App\Service\SluggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-user-pages',
    description: 'Synchronize CustomPage for all users of the site',
)]
class SyncUserPagesCommand extends Command
{
    protected function configure()
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run the command without actually creating pages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Synchronizing User Pages');

        // Get the user CustomPageModel
        $customPageModel = SessionContextService::getRepositoryFor(CustomPageModel::class)
            ->find(UserMapper::CPM_ID);

        if (!$customPageModel) {
            $io->error(sprintf('CustomPageModel with ID %d not found', UserMapper::CPM_ID));
            return Command::FAILURE;
        }

        $io->info(sprintf('Using CustomPageModel: %s (ID: %d)', $customPageModel->getName(), $customPageModel->getId()));

        // Get all users from the site using the same DQL as ImportCommand
        $site = EnvService::get_env('SITE');
        $dql = 'SELECT e FROM ' . User::class . ' e LEFT JOIN e.originDomain d WHERE e.enabled = true AND (e.remoteOrigin = :site OR d.name = :site)';

        $query = SessionContextService::getManagerFor(User::class)->createQuery($dql);
        $query->setParameter('site', $site);

        $users = $query->getResult();
        $totalUsers = count($users);

        $io->info(sprintf('Found %d enabled users for site %s', $totalUsers, $site));

        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            /** @var User $user */
            $io->text(sprintf('Processing user: %s (%s)', $user->getFullName(), $user->getEmail()));

            // Check if user already has a CustomPage linked via remoteUuid
            $existingPage = SessionContextService::getRepositoryFor(CustomPage::class)
                ->createQueryBuilder('cp')
                ->where('cp.customPageModel = :cpm')
                ->andWhere('cp.remoteUuid = :remoteUuid')
                ->andWhere('cp.tmp = false')
                ->setParameter('cpm', $customPageModel)
                ->setParameter('remoteUuid', $user->getUuid())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingPage) {
                $existingPage->regenerateSlug();
                $existingPage->save(false);
                $io->text(sprintf('  ✓ Page already exists (ID: %d, slug: %s)', $existingPage->getId(), $existingPage->getSlug()));
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $io->text('  → Would create page (dry-run mode)');
                $created++;
                continue;
            }

            // Create new CustomPage
            $customPage = new CustomPage();
            SessionContextService::getManagerFor(CustomPage::class)->persist($customPage);

            // Set basic properties
            $customPage
                ->setTmp(false)
                ->setCustomPageModel($customPageModel)
                ->setRemoteUuid($user->getUuid())
                ->setAuthorId($user->getId())
                ->setTitle($user->getFullName());

            // Generate slug from pseudo or full name
            $mapper = $user->getMapper();
            $pseudo = $mapper->getPseudo();
            $slugBase = !empty($pseudo) ? $pseudo : $user->getFullName();
            $slug = SluggerService::slugify($slugBase);

            // Ensure unique slug
            $finalSlug = $slug;
            $counter = 1;
            while ($this->slugExists($finalSlug, $customPage->getId())) {
                $finalSlug = $slug . '-' . $counter;
                $counter++;
            }

            $customPage->setSlug($finalSlug);
            $customPage->regenerateSlug();

            // Initialize the mapper with user data
            $pageMapper = $customPage->getMapper();
            if ($pageMapper) {
                // Sync mapper data from user mapper
                $userMapperData = $user->getMapperData();
                if (!empty($userMapperData)) {
                    $customPage->setMapperData($userMapperData);
                }
                $pageMapper->map();
            }

            $customPage->regenerateSlug();
            $customPage->save(false);

            $io->text(sprintf('  ✓ Created page: %s (slug: %s)', $user->getFullName(), $finalSlug));
            $created++;
        }

        if (!$dryRun) {
            SessionContextService::getManagerFor(CustomPage::class)->flush();
            $io->success(sprintf(
                'Synchronization complete: %d created, %d skipped',
                $created,
                $skipped
            ));
        } else {
            $io->success(sprintf(
                'Dry-run complete: would create %d pages, %d skipped',
                $created,
                $skipped
            ));
        }

        return Command::SUCCESS;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = SessionContextService::getRepositoryFor(CustomPage::class)
            ->createQueryBuilder('cp')
            ->where('cp.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('cp.id != :id')
                ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
