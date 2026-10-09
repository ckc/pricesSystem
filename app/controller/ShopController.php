<?php
namespace app\controller;

use support\Request;
use support\Controller;

use Respect\Validation\Validator;
use Respect\Validation\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use app\model\{Shops as ShopsModel};

class ShopController extends Controller
{

    public function info(Request $request, string $name)
    {
        try {
            $name = urldecode($name);
            $data = Validator::input([
                'name' => $name
            ], [
                'name' => Validator::StringType()->length(3, 128)->NotEmpty()->setName('Shops zh-tw name')
            ]);
            $info = ShopsModel::query()->where('name_chi', 'like', '%'.$data['name'].'%')->orWhere('name_en', 'like', '%'.$data['name'].'%')->firstOrFail();
            return $this->dataJson([
                'id' => $info->id,
                'name' => $info->name_chi,
                'name_en' => $info->name_en,
				'address' => $info->address_chi,
                'address_en' => $info->address_en,
                'ares' => $info->ares_chi,
				'ares_en' => $info->ares_en
            ]);
        } catch (ValidationException $e) {
            return $this->messageJson(404, 'Shop not found');
        } catch (ModelNotFoundException $e) {
            return $this->messageJson(404, 'Shop not found');
        }
    }

    public function infoEdit(Request $request, int $id)
    {
        try {
            $data = Validator::input($request->post(), [
                'name' => Validator::stringType()->length(6, 128)->NotEmpty()->setName('Shops zh-tw name'),
                'name_en' => Validator::stringType()->length(6, 128)->NotEmpty()->setName('Shops en name'),
                'address' => Validator::stringType()->NotEmpty()->setName('address desc info'),
				'address_en' => Validator::stringType()->NotEmpty()->setName('address en desc info'),
                'ares' => Validator::stringType()->NotEmpty()->setName('ares desc info'),
                'ares_en' => Validator::stringType()->NotEmpty()->setName('ares en desc info')
            ]);
            $info = ShopsModel::query()->findOrFail($id);
            $info->name_chi = $data['name'];
            $info->name_en = $data['name_en'];
            $info->address_chi = $data['address'];
			$info->address_en = $data['address_en'];
			$info->ares_chi = $data['ares'];
            $info->ares_en = $data['ares_en'];
            $info->save();
            return $this->dataJson(['id' => $info->id]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            return $this->messageJson(404, $e->getMessage());
        }
    }

    public function infoPost(Request $request)
    {
        try {
            $data = Validator::input($request->post(), [
                'name' => Validator::stringType()->length(6, 128)->NotEmpty()->setName('Shops zh-tw name'),
                'name_en' => Validator::stringType()->length(6, 128)->NotEmpty()->setName('Shops en name'),
                'address' => Validator::stringType()->NotEmpty()->setName('address desc info'),
                'address_en' => Validator::stringType()->NotEmpty()->setName('address en desc info'),
                'ares' => Validator::stringType()->NotEmpty()->setName('ares desc info'),
                'ares_en' => Validator::stringType()->NotEmpty()->setName('ares en desc info')
            ]);
            $info = new ShopsModel;
            $info->name_chi = $data['name'];
            $info->name_en = $data['name_en'];
            $info->address_chi = $data['address'];
            $info->address_en = $data['address_en'];
            $info->ares_chi = $data['ares'];
            $info->ares_en = $data['ares_en'];
            $info->save();
            return $this->dataJson(['id' => $info->id]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        }
    }
    
}
