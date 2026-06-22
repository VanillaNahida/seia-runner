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
class AipImageClassify extends AipBase {

    /**
     * @var string
     */
    private $traffic_flowUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/traffic_flow';

    /**
     * @var string
     */
    private $vehicle_segUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/vehicle_seg';

    /**
     * @var string
     */
    private $vehicle_detect_highUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/vehicle_detect_high';

    /**
     * @var string
     */
    private $vehicle_attrUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/vehicle_attr';

    /**
     * 通用物体识别 advanced_general api url
     * @var string
     */
    private $advancedGeneralUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v2/advanced_general';

    /**
     * 菜品识别 dish_detect api url
     * @var string
     */
    private $dishDetectUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v2/dish';

    /**
     * 车辆识别 car_detect api url
     * @var string
     */
    private $carDetectUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/car';

    /**
     * 车辆检测 vehicle_detect api url
     * @var string
     */
    private $vehicleDetectUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/vehicle_detect';

    /**
     * 车辆外观损伤识别 vehicle_damage api url
     * @var string
     */
    private $vehicleDamageUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/vehicle_damage';

    /**
     * logo商标识别 logo_search api url
     * @var string
     */
    private $logoSearchUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v2/logo';

    /**
     * logo商标识别—添加 logo_add api url
     * @var string
     */
    private $logoAddUrl = 'https://aip.baidubce.com/rest/2.0/realtime_search/v1/logo/add';

    /**
     * logo商标识别—删除 logo_delete api url
     * @var string
     */
    private $logoDeleteUrl = 'https://aip.baidubce.com/rest/2.0/realtime_search/v1/logo/delete';

    /**
     * 动物识别 animal_detect api url
     * @var string
     */
    private $animalDetectUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/animal';

    /**
     * 植物识别 plant_detect api url
     * @var string
     */
    private $plantDetectUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/plant';

    /**
     * 图像主体检测 object_detect api url
     * @var string
     */
    private $objectDetectUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/object_detect';

    /**
     * 地标识别 landmark api url
     * @var string
     */
    private $landmarkUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/landmark';

    /**
     * 花卉识别 flower api url
     * @var string
     */
    private $flowerUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/flower';

    /**
     * 食材识别 ingredient api url
     * @var string
     */
    private $ingredientUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/classify/ingredient';

    /**
     * 红酒识别 redwine api url
     * @var string
     */
    private $redwineUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/redwine';

    /**
     * 货币识别 currency api url
     * @var string
     */
    private $currencyUrl = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/currency';

    /**
     * 菜品识别-添加
     * @var string
     */
    private $customDishAddUrl = "https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/dish/add";
    /**
     * 菜品识别-搜索
     * @var string
     */
    private $customDishSearchUrl = "https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/dish/search";
    /**
     * 菜品识别-删除
     * @var string
     */
    private $customDishDeleteUrl = "https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/dish/delete";
    /**
     * 多目标识别
     * @var string
     */
    private $multiObjectDetectUrl = "https://aip.baidubce.com/rest/2.0/image-classify/v1/multi_object_detect";
    /**
     * 组合接口
     * @var string
     */
    private $combinationUrl = "https://aip.baidubce.com/api/v1/solution/direct/imagerecognition/combination";

    private $redwineAddV1Url = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/redwine/add';
    private $redwineSearchV1Url = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/redwine/search';
    private $redwineDeleteV1Url = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/redwine/delete';
    private $redwineUpdateV1Url = 'https://aip.baidubce.com/rest/2.0/image-classify/v1/realtime_search/redwine/update';
    private $vehicleAttrClassifyV2Url = 'https://aip.baidubce.com/rest/2.0/image-classify/v2/vehicle_attr';



    /**
     * 通用物体识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Xk3bcxe21
     */
    public function advancedGeneral($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->advancedGeneralUrl, $data);
    }

    /**
     * 菜品识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/tk3bcxbb0
     */
    public function dishDetect($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->dishDetectUrl, $data);
    }

    /**
     * 车型识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/tk3hb3eiv
     */
    public function carDetect($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->carDetectUrl, $data);
    }

    /**
     * 车辆检测接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/rk3hb3flg
     */
    public function vehicleDetect($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->vehicleDetectUrl, $data);
    }

    /**
     * 车辆外观损伤识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/fk3hb3f5w
     */
    public function vehicleDamage($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->vehicleDamageUrl, $data);
    }

    /**
     * logo商标识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Ok3bcxc59
     */
    public function logoSearch($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->logoSearchUrl, $data);
    }

    /**
     * logo商标识别—添加接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Ok3bcxc59
     * @return array
     */
    public function logoAdd($image, $brief, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);
        $data['brief'] = $brief;

        $data = array_merge($data, $options);

        return $this->request($this->logoAddUrl, $data);
    }

    /**
     * logo商标识别—删除接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Ok3bcxc59
     */
    public function logoDeleteByImage($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->logoDeleteUrl, $data);
    }

    /**
     * logo商标识别—删除接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Ok3bcxc59
     */
    public function logoDeleteBySign($contSign, $options=array()){

        $data = array();
        
        $data['cont_sign'] = $contSign;

        $data = array_merge($data, $options);

        return $this->request($this->logoDeleteUrl, $data);
    }

    /**
     * 动物识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Zk3bcxdfr
     */
    public function animalDetect($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->animalDetectUrl, $data);
    }

    /**
     * 植物识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Mk3bcxe9i
     */
    public function plantDetect($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->plantDetectUrl, $data);
    }

    /**
     * 图像主体检测接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Xk3bcxdum
     */
    public function objectDetect($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->objectDetectUrl, $data);
    }

    /**
     * 地标识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/jk3bcxbih
     */
    public function landmark($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->landmarkUrl, $data);
    }

    /**
     * 花卉识别接口
     *
     * @param string $image - 图像数据，要求base64编码后大小不超过4M，最短边至少15px，最长边最大4096px,支持jpg/png/bmp格式
     * @param array $options - 可选参数对象，key: value都为string类型
     * @description options列表:
     *   top_num 返回预测得分top结果数，默认为5
     *   baike_num 返回百科信息的结果数，默认不返回
     * @return array
     */
    public function flower($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->flowerUrl, $data);
    }

    /**
     * 食材识别接口
     *
     * @param string $image - 图像数据，要求base64编码后大小不超过4M，最短边至少15px，最长边最大4096px,支持jpg/png/bmp格式
     * @param array $options - 可选参数对象，key: value都为string类型
     * @description options列表:
     *   top_num 返回预测得分top结果数，如果为空或小于等于0默认为5；如果大于20默认20
     * @return array
     */
    public function ingredient($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->ingredientUrl, $data);
    }

    /**
     * 红酒识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Tk3bcxctf
     */
    public function redwine($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->redwineUrl, $data);
    }

    /**
     * 货币识别接口
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/pk3bcxavy
     */
    public function currency($image, $options=array()){

        $data = array();
        
        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->currencyUrl, $data);
    }

    /**
     * 自定义菜品识别—入库
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Sk3bcxcd0
     */
    public function customDishesAddImage($image, $brief, $options=array()){

        $data = array();

        $data['image'] = base64_encode($image);

        $data['brief'] = $brief;

        $data = array_merge($data, $options);

        return $this->request($this->customDishAddUrl, $data);
    }


    /**
     * 自定义菜品识别—检索
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Sk3bcxcd0
     */
    public function customDishesSearch($image, $options=array()){

        $data = array();

        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->customDishSearchUrl, $data);
    }

    /**
     * 自定义菜品识别—删除
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Sk3bcxcd0
     */
    public function customDishesDeleteImage($image, $options=array()){

        $data = array();

        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->customDishDeleteUrl, $data);
    }



    /**
     * 自定义菜品识别—删除
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Sk3bcxcd0
     */
    public function customDishesDeleteContSign($contSign, $options=array()){

        $data = array();

        $data['cont_sign'] = $contSign;

        $data = array_merge($data, $options);

        return $this->request($this->customDishDeleteUrl, $data);
    }



    /**
     * 图像多主体检测
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Wk7em3moi
     */
    public function multiObjectDetect($image, $options=array()){

        $data = array();

        $data['image'] = base64_encode($image);

        $data = array_merge($data, $options);

        return $this->request($this->multiObjectDetectUrl, $data);
    }



    /**
     * 组合接口-image
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Kkbg3gxs7
     */
    public function combinationByImage($image, $scenes, $options=array()){

        $data = array();

        $data['image'] = base64_encode($image);
        $data['scenes'] = $scenes;

        $data = array_merge($data, $options);

        return $this->request($this->combinationUrl, json_encode($data), array('Content-Type' => 'application/json;charset=utf-8'));
    }



    /**
     * 组合接口-imageUrl
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/Kkbg3gxs7
     */
    public function combinationByImageUrl($imageUrl, $scenes, $options=array()){

        $data = array();

        $data['imgUrl'] = $imageUrl;
        $data['scenes'] = $scenes;

        $data = array_merge($data, $options);

        return $this->request($this->combinationUrl, json_encode($data), array('Content-Type' => 'application/json;charset=utf-8'));
    }




    /**
     * 车辆属性识别
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/mk3hb3fde
     */
    public function vehicleAttr($image, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['image'] = base64_encode($image);

        return $this->request($this->vehicle_attrUrl, $data);
    }


    /**
     * 车辆属性识别
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/mk3hb3fde
     */
    public function vehicleAttrUrl($url, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['url'] = $url;

        return $this->request($this->vehicle_attrUrl, $data);
    }


    /**
     * 车辆检测-高空版
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/Dk50qg8mm
     */
    public function vehicleDetectHigh($image, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['image'] = base64_encode($image);

        return $this->request($this->vehicle_detect_highUrl, $data);
    }


    /**
     * 车辆检测-高空版
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/Dk50qg8mm
     */
    public function vehicleDetectHighUrl($url, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['url'] = $url;

        return $this->request($this->vehicle_detect_highUrl, $data);
    }


    /**
     * 车型识别
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/tk3hb3eiv
     */
    public function carDetectUrl($url, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['url'] = $url;

        return $this->request($this->carDetectUrl, $data);
    }


    /**
     * 车辆检测
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/rk3hb3flg
     */
    public function vehicleDetectUrl($url, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['url'] = $url;

        return $this->request($this->vehicle_detectUrl, $data);
    }


    /**
     * 车辆分割
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/dk3hb3eyf
     */
    public function vehicleSeg($image, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['image'] = base64_encode($image);

        return $this->request($this->vehicle_segUrl, $data);
    }


    /**
     * 车流统计
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/yk3hb3eqk
     */
    public function trafficFlow($image, $case_id, $case_init, $area, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['image'] = base64_encode($image);
        $data['case_id'] = $case_id;
        $data['case_init'] = $case_init;
        $data['area'] = $area;

        return $this->request($this->traffic_flowUrl, $data);
    }


    /**
     * 车流统计
     * 接口使用说明文档: https://ai.baidu.com/ai-doc/VEHICLE/yk3hb3eqk
     */
    public function trafficFlowUrl($url, $case_id, $case_init, $area, $options=array()){

        $data = array();

        $data = array_merge($data, $options);
        $data['url'] = $url;
        $data['case_id'] = $case_id;
        $data['case_init'] = $case_init;
        $data['area'] = $area;

        return $this->request($this->traffic_flowUrl, $data);
    }

    /**
     * 自定义红酒识别--入库
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92-%E5%85%A5%E5%BA%93
     */
    public function redwineAddV1ByImage($image, $brief, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data['brief'] = $brief;
        $data = array_merge($data, $options);
        return $this->request($this->redwineAddV1Url, $data);
    }

    /**
     * 自定义红酒识别--入库
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92-%E5%85%A5%E5%BA%93
     */
    public function redwineAddV1ByUrl($url, $brief, $options=array()){
        $data = array();
        $data['url'] = $url;
        $data['brief'] = $brief;
        $data = array_merge($data, $options);
        return $this->request($this->redwineAddV1Url, $data);
    }

    /**
     * 自定义红酒识别--检索
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92-%E6%A3%80%E7%B4%A2
     */
    public function redwineSearchV1ByImage($image, $custom_lib, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data['custom_lib'] = $custom_lib;
        $data = array_merge($data, $options);
        return $this->request($this->redwineSearchV1Url, $data);
    }

    /**
     * 自定义红酒识别--检索
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92-%E6%A3%80%E7%B4%A2
     */
    public function redwineSearchV1ByUrl($url, $custom_lib, $options=array()){
        $data = array();
        $data['url'] = url;
        $data['custom_lib'] = custom_lib;
        $data = array_merge($data, $options);
        return $this->request($this->redwineSearchV1Url, $data);
    }

    /**
     * 自定义红酒识别--删除
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92-%E5%88%A0%E9%99%A4
     */
    public function redwineDeleteV1ByImage($image, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data = array_merge($data, $options);
        return $this->request($this->redwineDeleteV1Url, $data);
    }

    /**
     * 自定义红酒识别--删除
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92-%E5%88%A0%E9%99%A4
     */
    public function redwineDeleteV1BySign($sign, $options=array()){
        $data = array();
        $data['cont_sign_list'] = $sign;
        $data = array_merge($data, $options);
        return $this->request($this->redwineDeleteV1Url, $data);
    }

    /**
     * 自定义红酒识别--更新
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92%E6%9B%B4%E6%96%B0
     */
    public function redwineUpdateV1($image, $brief, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data['brief'] = $brief;
        $data = array_merge($data, $options);
        return $this->request($this->redwineUpdateV1Url, $data);
    }

    /**
     * 自定义红酒识别--更新
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92%E6%9B%B4%E6%96%B0
     */
    public function redwineUpdateV1ByImage($image, $brief, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data['brief'] = $brief;
        $data = array_merge($data, $options);
        return $this->request($this->redwineUpdateV1Url, $data);
    }

    /**
     * 自定义红酒识别--更新
     * 接口使用说明: https://ai.baidu.com/ai-doc/IMAGERECOGNITION/skh4k58o4#%E8%87%AA%E5%AE%9A%E4%B9%89%E7%BA%A2%E9%85%92%E6%9B%B4%E6%96%B0
     */
    public function redwineUpdateV1ByUrl($url, $brief, $options=array()){
        $data = array();
        $data['url'] = $url;
        $data['brief'] = $brief;
        $data = array_merge($data, $options);
        return $this->request($this->redwineUpdateV1Url, $data);
    }

    /**
     * 车辆属性识别
     * 接口使用说明: https://ai.baidu.com/ai-doc/VEHICLE/mk3hb3fde
     */
    public function vehicleAttrClassifyV2ByImage($image, $options=array()){
        $data = array();
        $data['image'] = base64_encode($image);
        $data = array_merge($data, $options);
        return $this->request($this->vehicleAttrClassifyV2Url, $data);
    }

    /**
     * 车辆属性识别
     * 接口使用说明: https://ai.baidu.com/ai-doc/VEHICLE/mk3hb3fde
     */
    public function vehicleAttrClassifyV2ByUrl($url, $options=array()){
        $data = array();
        $data['url'] = $url;
        $data = array_merge($data, $options);
        return $this->request($this->vehicleAttrClassifyV2Url, $data);
    }

}