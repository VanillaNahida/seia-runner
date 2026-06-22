<?php
/*
* Copyright (c) 2017 Baidu.com, Inc. All Rights Reserved
*
* Licensed under the Apache License, Version 2.0 (the "License"); you may not
* use this file except in compliance with the License. You may obtain a copy of
* the License at
*
* Http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
* WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
* License for the specific language governing permissions and limitations under
* the License.
*/

require_once 'lib/AipBase.php';
class AipBodyAnalysis extends AipBase {

    /**
     * 人体关键点识别 body_analysis api url
     * @var string
     */
    private $bodyAnalysisUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/body_analysis';

    /**
     * 人体检测与属性识别 body_attr api url
     * @var string
     */
    private $bodyAttrUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/body_attr';

    /**
     * 人流量统计 body_num api url
     * @var string
     */
    private $bodyNumUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/body_num';

    /**
     * 手势识别 gesture api url
     * @var string
     */
    private $gestureUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/gesture';

    /**
     * 人像分割 body_seg api url
     * @var string
     */
    private $bodySegUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/body_seg';

    /**
     * 驾驶行为分析 driver_behavior api url
     * @var string
     */
    private $driverBehaviorUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/driver_behavior';

    /**
     * 人流量统计-动态版 body_tracking api url
     * @var string
     */
    private $bodyTrackingUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/body_tracking';

    /**
     * 手部关键点识别 hand_analysis api url
     * @var string
     */
    private $handAnalysisUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/hand_analysis';

    private $bodyDangerV1Url = 'https://aip.baidubce.com/rest/2.0/video-classify/v1/body_danger';
    private $fingertipV1Url = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/fingertip';
    

    /**
     * 人体关键点识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/0k3cpyxme
     */
    public function bodyAnalysis($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->bodyAnalysisUrl, $data);
    }

    /**
     * 人体检测与属性识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/Ak3cpyx6v
     */
    public function bodyAttr($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->bodyAttrUrl, $data);
    }

    /**
     * 人流量统计接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/7k3cpyy1t
     */
    public function bodyNum($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->bodyNumUrl, $data);
    }

    /**
     * 手势识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/4k3cpywrv
     */
    public function gesture($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->gestureUrl, $data);
    }

    /**
     * 人像分割接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/Fk3cpyxua
     */
    public function bodySeg($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->bodySegUrl, $data);
    }

    /**
     * 驾驶行为分析接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/Nk3cpywct
     */
    public function driverBehavior($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->driverBehaviorUrl, $data);
    }

    /**
     * 人流量统计-动态版接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/wk3cpyyog
     */
    public function bodyTracking($image, $dynamic, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);
        $data['dynamic'] = $dynamic;

        $data = array_merge($data, $options);

        return $this->request($this->bodyTrackingUrl, $data);
    }

    /**
     * 手部关键点识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/Kk3cpyxeu
     */
    public function handAnalysis($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->handAnalysisUrl, $data);
    }

    /**
     * 危险行为识别
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/uk3cpywke
     */
    public function bodyDangerV1($videoData, $options=array()){
        $data = array();
        $data['data'] = base64_encode($videoData);
        $data = array_merge($data, $options);
        return $this->request($this->bodyDangerV1Url, $data);
    }

    /**
     * 指尖检测
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/BODY/Jk7ir38ut
     */
    public function fingertipV1($image, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data = array_merge($data, $options);
        return $this->request($this->fingertipV1Url, $data);
    }

}