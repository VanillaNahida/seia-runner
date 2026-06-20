<?php
if (basename($_SERVER["SCRIPT_FILENAME"]) === "geetest_lib.php") {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

/**
 * GT 4.0 验证结果封装
 */
class GeetestLibResult
{
    private $status = 0;
    private $data = "";
    private $msg = "";

    public function getStatus() { return $this->status; }
    public function setStatus($status) { $this->status = $status; }
    public function getData() { return $this->data; }
    public function setData($data) { $this->data = $data; }
    public function getMsg() { return $this->msg; }
    public function setMsg($msg) { $this->msg = $msg; }

    public function setAll($status, $data, $msg)
    {
        $this->setStatus($status);
        $this->setData($data);
        $this->setMsg($msg);
    }

    public function __toString()
    {
        return sprintf("GeetestLibResult{status=%s, data=%s, msg=%s}", $this->status, $this->data, $this->msg);
    }
}

/**
 * GT 4.0 SDK
 *
 * GT 4.0 不需要 register 步骤，前端直接初始化验证码，
 * 后端只需调用 validate 进行二次校验。
 */
class GeetestLib
{
    const API_URL = "https://gcaptcha4.geetest.com";
    const VALIDATE_URL = "/validate";
    const HTTP_TIMEOUT_DEFAULT = 5;

    private $captcha_id;
    private $captcha_key;
    private $libResult;

    public function __construct($captcha_id, $captcha_key)
    {
        $this->captcha_id = $captcha_id;
        $this->captcha_key = $captcha_key;
        $this->libResult = new GeetestLibResult();
    }

    /**
     * 二次验证
     *
     * @param string $lot_number     验证流水号
     * @param string $captcha_output 验证输出信息
     * @param string $pass_token     验证通过标识
     * @param string $gen_time       验证通过时间戳
     * @return GeetestLibResult
     */
    public function validate($lot_number, $captcha_output, $pass_token, $gen_time)
    {
        if (empty($lot_number) || empty($captcha_output) || empty($pass_token) || empty($gen_time)) {
            $this->libResult->setAll(0, "", "验证参数不完整");
            return $this->libResult;
        }

        // 生成签名: HMAC-SHA256(lot_number, captcha_key)
        $sign_token = hash_hmac("sha256", $lot_number, $this->captcha_key);

        $params = [
            "lot_number"     => $lot_number,
            "captcha_output" => $captcha_output,
            "pass_token"     => $pass_token,
            "gen_time"       => $gen_time,
            "sign_token"     => $sign_token,
        ];

        $url = self::API_URL . self::VALIDATE_URL . "?captcha_id=" . urlencode($this->captcha_id);

        try {
            $resBody = $this->httpPost($url, $params);
            $res_array = json_decode($resBody, true);

            if (!is_array($res_array)) {
                $this->libResult->setAll(0, "", "请求极验二次验证接口失败：响应解析失败");
                return $this->libResult;
            }

            if (isset($res_array["result"]) && $res_array["result"] === "success") {
                $this->libResult->setAll(1, "", "");
            } else {
                $reason = isset($res_array["reason"]) ? $res_array["reason"] : "未知原因";
                $this->libResult->setAll(0, "", "极验二次验证不通过：" . $reason);
            }
        } catch (\Throwable $t) {
            $this->libResult->setAll(0, "", "请求极验二次验证接口异常：" . $t->getMessage());
        }

        return $this->libResult;
    }

    /**
     * 本地降级验证（宕机模式）
     * 仅做参数非空校验
     */
    public function failValidate($lot_number, $captcha_output, $pass_token, $gen_time)
    {
        if (empty($lot_number) || empty($captcha_output) || empty($pass_token) || empty($gen_time)) {
            $this->libResult->setAll(0, "", "宕机模式：验证参数不完整");
        } else {
            $this->libResult->setAll(1, "", "");
        }
        return $this->libResult;
    }

    /**
     * POST 请求
     */
    private function httpPost($url, $param)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT_DEFAULT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type:application/x-www-form-urlencoded"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("HTTP status code: " . $httpCode);
        }

        return $res;
    }
}
