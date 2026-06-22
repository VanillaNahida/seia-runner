<?php
/**
 * 百度内容审核封装
 */

// 临时降级错误级别，抑制 Baidu SDK 在 PHP 8.2+ 的动态属性弃用警告
$oldErrorReporting = error_reporting(E_ALL & ~E_DEPRECATED);

$sdkDir = __DIR__ . '/sdk';
set_include_path($sdkDir . PATH_SEPARATOR . $sdkDir . '/lib' . PATH_SEPARATOR . get_include_path());
require_once $sdkDir . '/AipContentCensor.php';

// 降低 SDK 超时：连接 3 秒，传输 5 秒

/**
 * 使用百度云内容审核检测文本
 *
 * @param string $text 待检测文本
 * @param string $fieldName 字段名称（用于错误提示）
 * @return array [
 *   'pass' => true|false,
 *   'reason' => '违规理由' (pass=false 时),
 *   'field' => '字段名称' (pass=false 时),
 *   'hit' => '命中原文' (pass=false 时)
 * ]
 */
function baiduTextCensor($text, $fieldName) {
    global $oldErrorReporting;
    $prevLevel = error_reporting($oldErrorReporting);

    try {
        $client = new AipContentCensor(BAIDU_APP_ID, BAIDU_API_KEY, BAIDU_SECRET_KEY);
        $client->setConnectionTimeoutInMillis(3000);
        $client->setSocketTimeoutInMillis(5000);

        $data = ['text' => $text];
        if (defined('BAIDU_CENSOR_STRATEGY_ID') && BAIDU_CENSOR_STRATEGY_ID !== '') {
            $data['strategyId'] = BAIDU_CENSOR_STRATEGY_ID;
        }

        $url = 'https://aip.baidubce.com/rest/2.0/solution/v1/text_censor/v2/user_defined';
        $result = $client->post($url, $data);
    } catch (\Throwable $t) {
        error_log("[BaiduCensor] exception: " . $t->getMessage());
        error_reporting($prevLevel);
        return ['pass' => true];
    }

    error_reporting($prevLevel);

    // 检查 API 调用是否出错
    if (isset($result['error_code'])) {
        error_log("[BaiduCensor] API error: " . ($result['error_msg'] ?? 'unknown'));
        // API 出错时放行，不影响用户正常使用
        return ['pass' => true];
    }

    // conclusionType: 1=合规, 2=不合规, 3=疑似
    $conclusionType = isset($result['conclusionType']) ? intval($result['conclusionType']) : 1;

    if ($conclusionType === 1) {
        return ['pass' => true];
    }

    // 不合规或疑似 — 收集违规理由
    $reasons = [];
    $hits = [];
    if (isset($result['data']) && is_array($result['data'])) {
        foreach ($result['data'] as $item) {
            $reasons[] = isset($item['msg']) ? $item['msg'] : '内容不合规';
            if (isset($item['hit']) && is_array($item['hit'])) {
                $hits = array_merge($hits, $item['hit']);
            }
        }
    }

    return [
        'pass' => false,
        'field' => $fieldName,
        'reason' => implode('；', $reasons) ?: '内容不合规',
        'hit' => implode('、', array_unique($hits)) ?: $text
    ];
}
