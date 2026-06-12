<?php

function loadWafRules(string $name): array {
    $path = __DIR__ . "/waf_rules/" . $name . ".json";
    if (!is_file($path)) {
        return [];
    }

    $rules = json_decode((string)file_get_contents($path), true);
    return is_array($rules) ? $rules : [];
}

function flattenWafInput($input): string {
    if (is_array($input)) {
        return urldecode(http_build_query($input));
    }

    return urldecode((string)$input);
}

function getRawWafBody(): string {
    $body = file_get_contents("php://input");
    if ($body === false) {
        return "";
    }

    return (string)$body;
}

function wafMatchRules(string $input, array $rules): ?array {
    foreach ($rules as $rule) {
        if (!is_array($rule) || count($rule) < 2 || intval($rule[0]) !== 1) {
            continue;
        }

        $pattern = "#" . $rule[1] . "#iu";
        $matched = @preg_match($pattern, $input);
        if ($matched === 1) {
            return $rule;
        }
    }

    return null;
}

function rejectWafRequest(array $rule): void {
    http_response_code(403);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "code" => 403,
        "message" => "Request blocked by WAF"
    ]);
    exit;
}

function runRequestWaf(): void {
    $checks = [
        [flattenWafInput($_GET), loadWafRules("args")],
        [flattenWafInput($_SERVER["REQUEST_URI"] ?? ""), loadWafRules("url")],
        [flattenWafInput($_POST), loadWafRules("post")],
        [flattenWafInput(getRawWafBody()), loadWafRules("post")],
        [flattenWafInput($_COOKIE), loadWafRules("cookie")]
    ];

    foreach ($checks as $check) {
        $matched = wafMatchRules($check[0], $check[1]);
        if ($matched !== null) {
            rejectWafRequest($matched);
        }
    }
}
