<?php
// Seed API permission for Leave Assistant chat endpoint.
// Usage: php installer/scripts/seed_leave_assistant.php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;

$autoload = __DIR__ . '/../../src/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Composer autoload not found. Run composer install -d src\n");
    exit(1);
}
require_once $autoload;

// Load DB credentials from Conf.php
require_once __DIR__ . '/../../lib/confs/Conf.php';
if (!class_exists('Conf')) {
    fwrite(STDERR, "Conf class not found at lib/confs/Conf.php\n");
    exit(1);
}
$conf = new Conf();

$params = [
    'dbname' => $conf->getDbName(),
    'user' => $conf->getDbUser(),
    'password' => $conf->getDbPass(),
    'host' => $conf->getDbHost(),
    'port' => $conf->getDbPort(),
    'driver' => 'pdo_mysql',
    'charset' => 'utf8mb4',
];

$conn = DriverManager::getConnection($params);
$conn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', Types::STRING);

$apiClass = 'OrangeHRM\\Leave\\Api\\LeaveAssistantAPI';
$dataGroupName = 'apiv2_leave_assistant_chat';

$conn->beginTransaction();
try {
    // Check api permission exists
    $exists = (int)$conn->createQueryBuilder()
        ->select('COUNT(1)')
        ->from('ohrm_api_permission')
        ->where('api_name = :api')
        ->setParameter('api', $apiClass)
        ->executeQuery()
        ->fetchOne();

    if ($exists > 0) {
        echo "Leave Assistant API permission already exists.\n";
        $conn->commit();
        exit(0);
    }

    // Insert data group
    $conn->createQueryBuilder()
        ->insert('ohrm_data_group')
        ->values([
            'name' => ':name',
            'description' => ':desc',
            'can_read' => ':r',
            'can_create' => ':c',
            'can_update' => ':u',
            'can_delete' => ':d',
        ])
        ->setParameter('name', $dataGroupName)
        ->setParameter('desc', 'Leave - Assistant Chat')
        ->setParameter('r', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
        ->setParameter('c', true, \Doctrine\DBAL\ParameterType::BOOLEAN)
        ->setParameter('u', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
        ->setParameter('d', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
        ->executeQuery();

    // Resolve IDs
    $dataGroupId = (int)$conn->createQueryBuilder()
        ->select('id')
        ->from('ohrm_data_group')
        ->where('name = :name')
        ->setParameter('name', $dataGroupName)
        ->setMaxResults(1)
        ->executeQuery()
        ->fetchOne();

    $moduleId = (int)$conn->createQueryBuilder()
        ->select('id')
        ->from('ohrm_module')
        ->where('name = :name')
        ->setParameter('name', 'leave')
        ->setMaxResults(1)
        ->executeQuery()
        ->fetchOne();

    // Map API to data group
    $conn->createQueryBuilder()
        ->insert('ohrm_api_permission')
        ->values([
            'api_name' => ':api',
            'module_id' => ':moduleId',
            'data_group_id' => ':dataGroupId',
        ])
        ->setParameter('api', $apiClass)
        ->setParameter('moduleId', $moduleId)
        ->setParameter('dataGroupId', $dataGroupId)
        ->executeQuery();

    // Grant create permission to Admin, ESS, Supervisor
    $roles = ['Admin', 'ESS', 'Supervisor'];
    foreach ($roles as $role) {
        $roleId = (int)$conn->createQueryBuilder()
            ->select('id')
            ->from('ohrm_user_role')
            ->where('name = :name')
            ->setParameter('name', $role)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        $conn->createQueryBuilder()
            ->insert('ohrm_user_role_data_group')
            ->values([
                'data_group_id' => ':dg',
                'user_role_id' => ':ur',
                'can_read' => ':r',
                'can_create' => ':c',
                'can_update' => ':u',
                'can_delete' => ':d',
                'self' => ':s',
            ])
            ->setParameter('dg', $dataGroupId)
            ->setParameter('ur', $roleId)
            ->setParameter('r', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setParameter('c', true, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setParameter('u', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setParameter('d', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->setParameter('s', $role === 'ESS', \Doctrine\DBAL\ParameterType::BOOLEAN)
            ->executeQuery();
    }

    $conn->commit();
    echo "Seeded Leave Assistant API permission successfully.\n";
} catch (Throwable $e) {
    $conn->rollBack();
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
