<?php

namespace OrangeHRM\Installer\Command;

use OrangeHRM\Framework\Console\Command as BaseCommand;
use OrangeHRM\Installer\Util\V1\DataGroupHelper;
use OrangeHRM\Installer\Util\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedLeaveAssistantPermissionCommand extends BaseCommand
{
    public function getCommandName(): string
    {
        return 'permissions:seed-leave-assistant';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = Connection::getConnection();
        $qb = $conn->createQueryBuilder();
        $exists = (int)$qb->select('COUNT(1)')
            ->from('ohrm_api_permission')
            ->where('api_name = :api')
            ->setParameter('api', 'OrangeHRM\\Leave\\Api\\LeaveAssistantAPI')
            ->executeQuery()
            ->fetchOne();

        if ($exists > 0) {
            $output->writeln('<info>Leave Assistant API permission already exists. Nothing to do.</info>');
            return Command::SUCCESS;
        }

        $helper = new DataGroupHelper($conn);
        $configPath = realpath(__DIR__ . '/../permission/custom/api-leave-assistant.yaml');
        if (!$configPath) {
            $output->writeln('<error>Configuration file not found: installer/permission/custom/api-leave-assistant.yaml</error>');
            return Command::FAILURE;
        }

        $helper->insertApiPermissions($configPath);
        $output->writeln('<info>Seeded Leave Assistant API permission successfully.</info>');
        return Command::SUCCESS;
    }
}
