<?php
namespace app\controller;

use support\Request;
use support\Controller;

use Respect\Validation\Validator;
use Respect\Validation\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use app\model\{Brand as BrandModel};

class BrandsController extends Controller
{

    public function info(Request $request, string $name)
    {
        try {
            $name = urldecode($name);
            $data = Validator::input([
                'name' => $name
            ], [
                'name' => Validator::StringType()->length(2, 128)->NotEmpty()->setName('Brand zh-tw name')
            ]);
            $info = BrandModel::query()->where('name_chi', 'like', '%'.$data['name'].'%')->orWhere('name_en', 'like', '%'.$data['name'].'%')->firstOrFail();
            return $this->dataJson([
                'id' => $info->id,
                'name' => $info->name_chi,
                'name_en' => $info->name_en,
				'country' => $info->country,
                'total_goods' => $info->total_goods,
                'desc' => $info->descText_chi,
				'desc_en' => $info->descText_en
            ]);
        } catch (ValidationException $e) {
            return $this->messageJson(404, 'Brands not found');
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
    }

    public function search(Request $request)
    {
        $get = $request->get();
		$get['keyword'] = urldecode($get['keyword'] ?? '');
        $data = Validator::input($get, [
            'keyword' => Validator::stringType()->NotEmpty()->setName('Keyword')
        ]);
        $list = BrandModel::query()->where('name_chi', 'like', '%'.$data['keyword'].'%')->orWhere('name_en', 'like', '%'.$data['keyword'].'%')->get();
        $brandList = $list->toArray();
        if(empty($brandList)) {
            return json(['code' => 404, 'msg' => 'result not found']);
        }
        $resultList = array();
        foreach($brandList as $key => $val) {
            $resultList[] = [
                'id' => $val['id'],
                'name' => $val['name_chi'],
                'name_en' => $val['name_en'],
                'country' => $val['country'],
                'total_goods' => $val['total_goods'],
                'desc' => $val['descText_chi'],
                'desc_en' => $val['descText_en']
            ];
        }
        return $this->dataJson($resultList);
    }

    public function infoEdit(Request $request, int $id)
    {
        try {
            $data = Validator::input($request->post(), [
                'name' => Validator::stringType()->length(2, 32)->NotEmpty()->setName('Brand zh-tw name'),
                'name_en' => Validator::stringType()->length(6, 128)->NotEmpty()->setName('Brand en name'),
                'desc' => Validator::stringType()->NotEmpty()->setName('Brand zh-tw desc info'),
				'desc_en' => Validator::stringType()->NotEmpty()->setName('Brand en desc info'),
				'country' => Validator::stringType()->setName('Brand country info')
            ]);
            $info = BrandModel::query()->findOrFail($id);
            $info->name_chi = $data['name'];
            $info->name_en = $data['name_en'];
            $info->descText_chi = $data['desc'];
			$info->descText_en = $data['desc_en'];
			$info->country = $data['country'];
            $info->save();
            return $this->dataJson(['id' => $info->id]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
    }

    public function infoPost(Request $request)
    {
        try {
            $data = Validator::input($request->post(), [
                'name' => Validator::stringType()->length(2, 32)->NotEmpty()->setName('Brand zh-tw name'),
                'name_en' => Validator::stringType()->length(2, 128)->NotEmpty()->setName('Brand en name'),
                'desc' => Validator::stringType()->NotEmpty()->setName('Brand desc info'),
				'desc_en' => Validator::stringType()->NotEmpty()->setName('Good desc info'),
				'country' => Validator::stringType()->setName('Brand country info')
            ]);



            $info = new BrandModel;
            $info->name_chi = $data['name'];
            $info->name_en = $data['name_en'];
            $info->descText_chi = $data['desc'];
			$info->descText_en = $data['desc_en'];
			$info->country = $data['country'];
            $info->save();
            return $this->dataJson(['id' => $info->id]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        }
    }
    
}
