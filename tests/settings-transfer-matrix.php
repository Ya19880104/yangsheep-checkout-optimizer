<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/src/Settings/YSSettingsManager.php';

$transferPath = dirname(__DIR__) . '/src/Settings/YSSettingsTransfer.php';
if (!is_file($transferPath)) {
    echo "FAIL production settings transfer class exists\n";
    echo "SUMMARY total=1 failed=1\n";
    exit(1);
}

require_once dirname(__DIR__) . '/src/CustomFields/YSCustomFieldDefinitions.php';
require_once $transferPath;

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;
use YangSheep\CheckoutOptimizer\Settings\YSSettingsTransfer;

$total = 0;
$failed = 0;

function check_transfer(bool $condition, string $message): void
{
    global $total, $failed;
    $total++;
    if ($condition) {
        echo "PASS {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$state = YSSettingsManager::DEFAULT_VALUES;
$integration = [
    'points_label'   => '購物金',
    'button_text'    => '點此兌換折扣',
    'available_text' => '目前有 {points} {label} 可用',
];
$writes = [];
$integrationWrites = [];
$failKey = null;
$failIntegration = false;

$transfer = new YSSettingsTransfer(
    static function (string $key) use (&$state) {
        return $state[$key] ?? null;
    },
    static function (string $key, $value) use (&$state, &$writes, &$failKey): bool {
        $writes[] = [$key, $value];
        if ($key === $failKey) {
            return false;
        }
        $state[$key] = $value;
        return true;
    },
    static function () use (&$integration): array {
        return $integration;
    },
    static function (array $value) use (&$integration, &$integrationWrites, &$failIntegration): bool {
        $integrationWrites[] = $value;
        if ($failIntegration) {
            $failIntegration = false;
            return false;
        }
        $integration = $value;
        return true;
    },
    static function (): void {}
);

$package = $transfer->export_package('1.7.5-dev', '2026-07-24T00:00:00+00:00');

check_transfer($package['format'] === YSSettingsTransfer::FORMAT, 'export has the expected format marker');
check_transfer($package['schema_version'] === YSSettingsTransfer::SCHEMA_VERSION, 'export has the current schema version');
check_transfer(array_keys($package['settings']) === YSSettingsManager::ALL_SETTING_KEYS, 'export contains every canonical setting in canonical order');
check_transfer($package['integrations']['wployalty'] === $integration, 'export contains the complete WPLoyalty runtime labels');

$valid = YSSettingsTransfer::validate_package($package);
check_transfer($valid['valid'] === true && $valid['errors'] === [], 'fresh export validates');

$state['yangsheep_checkout_button_bg_color'] = 'red;position:fixed';
$integration['button_text'] = '<script>alert(1)</script>';
$safeExport = $transfer->export_package('1.7.5-dev', '2026-07-24T00:00:00+00:00');
check_transfer(
    $safeExport['settings']['yangsheep_checkout_button_bg_color'] === YSSettingsManager::DEFAULT_VALUES['yangsheep_checkout_button_bg_color'],
    'export replaces invalid stored CSS with the canonical safe default'
);
check_transfer(
    $safeExport['integrations']['wployalty']['button_text'] === YSSettingsTransfer::WPLOYALTY_DEFAULTS['button_text'],
    'export replaces invalid stored integration labels with safe defaults'
);
$state = YSSettingsManager::DEFAULT_VALUES;
$integration = [
    'points_label'   => '購物金',
    'button_text'    => '點此兌換折扣',
    'available_text' => '目前有 {points} {label} 可用',
];

$unknown = $package;
$unknown['settings']['yangsheep_unknown_setting'] = 'yes';
check_transfer(YSSettingsTransfer::validate_package($unknown)['valid'] === false, 'unknown settings are rejected');

$missing = $package;
unset($missing['settings']['yangsheep_checkout_button_bg_color']);
check_transfer(YSSettingsTransfer::validate_package($missing)['valid'] === false, 'missing canonical settings are rejected');

$badSchema = $package;
$badSchema['schema_version'] = 999;
check_transfer(YSSettingsTransfer::validate_package($badSchema)['valid'] === false, 'unsupported schema versions are rejected');

$badCheckbox = $package;
$badCheckbox['settings']['yangsheep_checkout_tw_fields'] = true;
check_transfer(YSSettingsTransfer::validate_package($badCheckbox)['valid'] === false, 'checkbox values must be explicit yes or no strings');

$badColor = $package;
$badColor['settings']['yangsheep_checkout_button_bg_color'] = 'red;position:fixed';
check_transfer(YSSettingsTransfer::validate_package($badColor)['valid'] === false, 'CSS color injection is rejected');

$badRadius = $package;
$badRadius['settings']['yangsheep_checkout_block_border_radius'] = '12px;display:none';
check_transfer(YSSettingsTransfer::validate_package($badRadius)['valid'] === false, 'CSS radius injection is rejected');

$badCvs = $package;
$badCvs['settings']['yangsheep_cvs_shipping_methods'] = ['flat_rate:1', ['nested']];
check_transfer(YSSettingsTransfer::validate_package($badCvs)['valid'] === false, 'non-scalar CVS rate ids are rejected');

// v1.8.0 schema 2：建立帳號提示與結帳自訂欄位
check_transfer(
    $package['settings']['yangsheep_checkout_account_hint'] === YSSettingsManager::DEFAULT_VALUES['yangsheep_checkout_account_hint']
    && $package['settings']['yangsheep_checkout_custom_fields'] === [],
    'export carries the account hint and an empty custom field list by default'
);

$emptyHint = $package;
$emptyHint['settings']['yangsheep_checkout_account_hint'] = '';
check_transfer(YSSettingsTransfer::validate_package($emptyHint)['valid'] === true, 'an empty account hint is valid and disables the hint');

$htmlHint = $package;
$htmlHint['settings']['yangsheep_checkout_account_hint'] = '<b>建立帳號</b>';
check_transfer(YSSettingsTransfer::validate_package($htmlHint)['valid'] === false, 'the account hint rejects HTML');

$longHint = $package;
$longHint['settings']['yangsheep_checkout_account_hint'] = str_repeat('字', 201);
check_transfer(YSSettingsTransfer::validate_package($longHint)['valid'] === false, 'the account hint is limited to 200 characters');

$consent = [
    'id'            => 'cf_abc123',
    'type'          => 'checkbox',
    'label'         => '  我已閱讀並同意{popup}  ',
    'popup_link'    => '購買須知',
    'popup_content' => '商品出貨前會先確認庫存。',
    'placeholder'   => '不應保留',
    'position'      => 'before_submit',
    'required'      => 'yes',
    'enabled'       => 'yes',
];
$withFields = $package;
$withFields['settings']['yangsheep_checkout_custom_fields'] = [$consent];
$fieldValidation = YSSettingsTransfer::validate_package($withFields);
$normalizedConsent = $fieldValidation['data']['settings']['yangsheep_checkout_custom_fields'][0] ?? [];
check_transfer(
    $fieldValidation['valid'] === true
    && ($normalizedConsent['label'] ?? null) === '我已閱讀並同意{popup}'
    && ($normalizedConsent['placeholder'] ?? null) === ''
    && array_keys($normalizedConsent) === \YangSheep\CheckoutOptimizer\CustomFields\YSCustomFieldDefinitions::FIELD_KEYS,
    'a consent checkbox normalizes label whitespace, drops checkbox placeholders and keeps canonical keys'
);

foreach ([
    'unknown type'           => ['type' => 'select'],
    'unknown position'       => ['position' => 'header'],
    'empty label'            => ['label' => '   '],
    'two popup tokens'       => ['label' => '{popup}與{popup}'],
    'invalid id'             => ['id' => 'CF-1'],
    'non-flag required'      => ['required' => 'maybe'],
    'array label'            => ['label' => ['x']],
    'oversized label'        => ['label' => str_repeat('字', 201)],
] as $case => $override) {
    $badFields = $package;
    $badFields['settings']['yangsheep_checkout_custom_fields'] = [array_merge($consent, $override)];
    check_transfer(YSSettingsTransfer::validate_package($badFields)['valid'] === false, "custom fields reject {$case}");
}

$unknownFieldKey = $package;
$unknownFieldKey['settings']['yangsheep_checkout_custom_fields'] = [array_merge($consent, ['onclick' => 'x'])];
check_transfer(YSSettingsTransfer::validate_package($unknownFieldKey)['valid'] === false, 'custom fields reject unknown keys');

$duplicateIds = $package;
$duplicateIds['settings']['yangsheep_checkout_custom_fields'] = [$consent, $consent];
check_transfer(YSSettingsTransfer::validate_package($duplicateIds)['valid'] === false, 'custom fields reject duplicate ids');

$tooMany = $package;
$tooMany['settings']['yangsheep_checkout_custom_fields'] = [];
for ($i = 0; $i < 21; $i++) {
    $tooMany['settings']['yangsheep_checkout_custom_fields'][] = array_merge($consent, ['id' => 'cf_many' . $i]);
}
check_transfer(YSSettingsTransfer::validate_package($tooMany)['valid'] === false, 'custom fields are limited to 20 entries');

$scriptContent = $package;
$scriptContent['settings']['yangsheep_checkout_custom_fields'] = [array_merge($consent, ['popup_content' => '須知<script>alert(1)</script>'])];
$scriptValidation = YSSettingsTransfer::validate_package($scriptContent);
check_transfer(
    $scriptValidation['valid'] === true
    && !str_contains($scriptValidation['data']['settings']['yangsheep_checkout_custom_fields'][0]['popup_content'], '<script'),
    'popup content is reduced to allowed markup'
);

// v1.7.x（schema 1）匯出檔仍可匯入；新鍵不在檔案裡就保持現值
$schemaOne = $package;
$schemaOne['schema_version'] = 1;
unset($schemaOne['settings']['yangsheep_checkout_account_hint'], $schemaOne['settings']['yangsheep_checkout_custom_fields']);
check_transfer(YSSettingsTransfer::validate_package($schemaOne)['valid'] === true, 'a schema 1 package without the new keys validates');

$schemaOneWithNewKey = $schemaOne;
$schemaOneWithNewKey['settings']['yangsheep_checkout_custom_fields'] = [];
check_transfer(YSSettingsTransfer::validate_package($schemaOneWithNewKey)['valid'] === false, 'a schema 1 package cannot carry schema 2 keys');

$schemaTwoMissing = $package;
unset($schemaTwoMissing['settings']['yangsheep_checkout_custom_fields']);
check_transfer(YSSettingsTransfer::validate_package($schemaTwoMissing)['valid'] === false, 'a schema 2 package must carry every key');

$state['yangsheep_checkout_account_hint'] = '保留這段提示';
$state['yangsheep_checkout_custom_fields'] = [$normalizedConsent];
$schemaOne['settings']['yangsheep_checkout_button_bg_color'] = '#0a0b0c';
$schemaOneResult = $transfer->import_package($schemaOne);
check_transfer(
    $schemaOneResult['success'] === true
    && $state['yangsheep_checkout_button_bg_color'] === '#0a0b0c'
    && $state['yangsheep_checkout_account_hint'] === '保留這段提示'
    && $state['yangsheep_checkout_custom_fields'] === [$normalizedConsent],
    'importing a schema 1 package updates its keys and keeps schema 2 keys unchanged'
);
$state = YSSettingsManager::DEFAULT_VALUES;

$badIntegration = $package;
$badIntegration['integrations']['wployalty']['extra'] = 'unknown';
check_transfer(YSSettingsTransfer::validate_package($badIntegration)['valid'] === false, 'unknown WPLoyalty integration keys are rejected');

$htmlIntegration = $package;
$htmlIntegration['integrations']['wployalty']['button_text'] = '<strong>兌換</strong>';
check_transfer(YSSettingsTransfer::validate_package($htmlIntegration)['valid'] === false, 'WPLoyalty labels reject HTML instead of silently normalizing it');

$unknownPlaceholder = $package;
$unknownPlaceholder['integrations']['wployalty']['available_text'] = '目前有 {points} {label} {unsafe} 可用';
check_transfer(YSSettingsTransfer::validate_package($unknownPlaceholder)['valid'] === false, 'unknown WPLoyalty placeholders are rejected');

$missingPlaceholder = $package;
$missingPlaceholder['integrations']['wployalty']['available_text'] = '目前有 {points} 可用';
check_transfer(YSSettingsTransfer::validate_package($missingPlaceholder)['valid'] === false, 'required WPLoyalty placeholders cannot be omitted');

$updated = $package;
$updated['settings']['yangsheep_checkout_button_bg_color'] = '#123456';
$updated['settings']['yangsheep_cvs_shipping_methods'] = [' flat_rate:1 ', 'flat_rate:1', 'paynow_shipping_c2c_711:3'];
$updated['integrations']['wployalty']['button_text'] = '兌換購物金';
$result = $transfer->import_package($updated);
check_transfer($result['success'] === true, 'valid package imports successfully');
check_transfer($state['yangsheep_checkout_button_bg_color'] === '#123456', 'canonical setting is updated');
check_transfer(
    $state['yangsheep_cvs_shipping_methods'] === ['flat_rate:1', 'paynow_shipping_c2c_711:3'],
    'CVS rate ids are trimmed and deduplicated'
);
check_transfer($integration['button_text'] === '兌換購物金', 'WPLoyalty labels are updated');

$beforeRollbackState = $state;
$beforeRollbackIntegration = $integration;
$rollbackPackage = $transfer->export_package('1.7.5-dev', '2026-07-24T00:00:00+00:00');
$rollbackPackage['settings']['yangsheep_checkout_button_bg_color'] = '#abcdef';
$rollbackPackage['settings']['yangsheep_checkout_button_text_color'] = '#010203';
$rollbackPackage['integrations']['wployalty']['button_text'] = '不應留下';
$failKey = 'yangsheep_checkout_button_text_color';
$rollbackResult = $transfer->import_package($rollbackPackage);
$failKey = null;

check_transfer($rollbackResult['success'] === false && $rollbackResult['rolled_back'] === true, 'failed write reports a successful rollback');
check_transfer($state === $beforeRollbackState, 'failed import restores every canonical setting');
check_transfer($integration === $beforeRollbackIntegration, 'failed import restores WPLoyalty integration settings');

$integrationRollbackPackage = $transfer->export_package('1.7.5-dev', '2026-07-24T00:00:00+00:00');
$integrationRollbackPackage['settings']['yangsheep_checkout_button_bg_color'] = '#112233';
$integrationRollbackPackage['integrations']['wployalty']['button_text'] = '不應寫入';
$failIntegration = true;
$integrationRollbackResult = $transfer->import_package($integrationRollbackPackage);

check_transfer(
    $integrationRollbackResult['success'] === false && $integrationRollbackResult['rolled_back'] === true,
    'integration write failure reports a successful rollback'
);
check_transfer($state === $beforeRollbackState, 'integration write failure restores canonical settings');
check_transfer($integration === $beforeRollbackIntegration, 'integration write failure leaves prior integration settings intact');

$invalidJson = $transfer->import_json('{broken');
check_transfer($invalidJson['success'] === false, 'invalid JSON is rejected without writes');

$oversizedJson = str_repeat('x', YSSettingsTransfer::MAX_JSON_BYTES + 1);
check_transfer($transfer->import_json($oversizedJson)['success'] === false, 'oversized JSON is rejected before parsing');

echo "SUMMARY total={$total} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
