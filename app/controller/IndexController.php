<?php
namespace app\controller;

use support\Request;
use support\Controller;
use app\model\{Types as TypesModel};
class IndexController extends Controller
{
    public function index(Request $request)
    {
        return response('hello, welcome to goods API SERVICES');
    }
	
	public function config(Request $request)
	{
		$list = TypesModel::query()->get();
		$typeList = [];
		foreach($list as $name) {
			if($name['level'] == 0) {
				$typeList[$name['id']] = [
					'id' => $name['id'],
					'name' => $name['name'],
					'en_name' => $name['en_name'],
					'sub' => []
				];
			}
		}
		foreach($list as $name) {
			if($name['level'] == 1 && isset($typeList[$name['pid']])) {
				$typeList[$name['pid']]['sub'][] = [
					'id' => $name['id'],
					'name' => $name['name'],
					'en_name' => $name['en_name'],
				];
			}
		}
		return $this->dataJson([
			'cate' => array_values($typeList)
		]);
	}
    
}
