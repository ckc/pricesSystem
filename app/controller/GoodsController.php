<?php
namespace app\controller;

use support\Request;
use support\Controller;

use Respect\Validation\Validator;
use Respect\Validation\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use app\model\{Brand as BrandModel, Goods as GoodsModel, Prices as PricesModel, PricesLog as PricesLogModel, Types as TypesModel, Files as FilesModel, GoodRack as GoodRackModel, GoodsRackInfo as GoodsRackInfoModel};
use app\helpers\ItemBarcode;
use Yurun\Util\Chinese;
use Binaryoung\Jieba\Jieba;

class GoodsController extends Controller
{
	
	private $page = 1;
    private $limit = 25;

    private function getOffset() : int {
        return intval(($this->page-1)*$this->limit);
    }

    private function getPage(Request $request) {
        $get = $request->get();
        if(isset($get['page'])) {
            $this->page = $get['page'] < 1 ? 1 : intval($get['page']);
        }
        if(isset($get['limit'])) {
            $this->limit = $get['limit'] < 1 ? 25 : intval($get['limit']);
        }
    }
	private string $keywordType = 'english';
	private array $keywords = [
		'type' => '',
		'value' => ''
	];
	private function autoMerge($english, $chinese) {
		$english = preg_replace('/\\d+/', '', $english);
		$chinese = preg_replace('/\\d+/', '', $chinese);
		$runWords = $english ? $english : $chinese;
		$this->keywordType = $english ? 'english' : 'chinese';
		if(!empty($runWords)) {
			if($this->keywordType == 'chinese') {
				$simpWord = Chinese::toSimplified($runWords);
				$words = Jieba::cut($simpWord[0]);
				foreach($words as $key => $val) {
					$words[$key] = Chinese::toTraditional($val)[0];
				}
			} else {
				$words = explode(' ', $runWords);
			}
			$words = array_filter($words, function($val, $k) {
				$val = trim($val);
				if(mb_strlen($val) < 2) {
					return false;
				} else {
					return true;
				}
			}, ARRAY_FILTER_USE_BOTH);
			if(count($words) > 3) {
				return array_slice($words, 0, 3);
			} else {
				if(empty($words)) {
					$words[] = $runWords;
				}
				return $words;
			}
		} else {
			return [];
		}
	}
	
	public function likes(Request $request)
    {
        $this->getPage($request);
		$get = $request->get();
		$query = GoodsModel::query();
		try {
			if(isset($get['gtin']) && !empty($get['gtin'])) {
				$gtin = $get['gtin'] ? strip_tags($get['gtin']) : '';
				$info = GoodsModel::query()->where('gtin', '=', $gtin)->firstOrFail();
				/*
				foreach($this->autoMerge($info->name_en, $info->name_chi) as $words) {
					$field = $this->keywordType == 'english' ? 'name_en' : 'name_chi';
					$query->orWhere($field, 'LIKE', $words.'%');
				}
				*/
				$query = $query->orWhere('gtin', '=', $gtin);
				$this->keywords = [
					'type' => 'gtin',
					'value' => $gtin
				];
			}
		} catch (ModelNotFoundException $e) {
			return json(['code' => 404, 'msg' => 'Goods not found']);
		}
		if((isset($get['en_name']) && !empty($get['en_name'])) || (isset($get['ch_name']) && !empty($get['ch_name']))) {
            $en_name = trim(strip_tags($get['en_name'] ?? ''));
			$ch_name = trim(strip_tags($get['ch_name'] ?? ''));
			if(empty($en_name) && empty($ch_name)) {
				return json(['code' => 40301, 'msg' => 'keyword doesnt found']);
			}
			if(!empty($en_name) && strlen($en_name) < 6) {
				return json(['code' => 40301, 'msg' => 'english keyword no less 6 letters']);
			} elseif(!empty($ch_name) && mb_strlen($ch_name) < 2) {
				return json(['code' => 40301, 'msg' => 'chinese keyword no less 2 letter']);
			}
			if((strlen($en_name) == 13 || strlen($en_name) == 12) || ctype_digit($en_name) || ctype_digit($ch_name)) {
				$query = $query->orWhere('gtin', '=', trim($en_name))->orWhere('gtin', '=', trim($ch_name));
				$this->keywords = [
					'type' => 'gtin',
					'value' => $en_name
				];
			}
			$result = $this->autoMerge($en_name, $ch_name);
			foreach($result as $words) {
				$field = $this->keywordType == 'english' ? 'name_en' : 'name_chi';
				if(strlen($words) > 2) {
					$query = $query->orWhere($field, 'LIKE', '%'.$words.'%');
				}
			}
			$this->keywords = [
				'type' => $this->keywordType,
				'value' => $result
			];
        }
		if(empty($this->keywords['type'])) {
			return json(['code' => 40301, 'msg' => 'all keyword doesnt found']);
		}
        $goodsList = $query->orderBy('id', 'desc')->offset($this->getOffset())->limit($this->limit)->get();
        if(empty($goodsList)) {
            return $this->dataJson(['list' => null]);
        }
        $brandId = array();
        foreach($goodsList as $goods) {
            if(!in_array($goods['brand'], $brandId)) {
                $brandId[] = $goods['brand'];
            }
        }
        $brandList = BrandModel::query()->whereIn('id', $brandId)->get();
        $brandList = array_column($brandList->toArray(), null, 'id');
        foreach($goodsList as $key => $goods) {
            $brandGoods = $brandList[$goods['brand']] ?? [
            	'name_chi' => '',
            	'name_en' => ''
            ];
            if($goods['files_path']) {
                $fileInfo = getimagesize(public_path().'/goods/'.$goods['files_path']);
                $goodsList[$key]['w'] = $fileInfo[0] ?? null;
                $goodsList[$key]['h'] = $fileInfo[1] ?? null;
            } else {
                $goodsList[$key]['w'] = null;
                $goodsList[$key]['h'] = null;
            }
			$goodsList[$key]['images'] = empty($goods['files_path']) ? null : 'https://img.goods.acghx.net/'.$goods['files_path'];
			$goodsList[$key]['store_image'] = empty($goods['store_files_path']) ? null : 'https://img.goods.acghx.net/'.$goods['store_files_path'];
            $goodsList[$key]['brand'] = [
                'id' => $goods['brand'],
                'name' => $brandGoods['name_chi'],
                'english' => $brandGoods['name_en']
            ];
        }
        return $this->dataJson(['list' => $goodsList,'search' => $this->keywords]);
    }
	
	
	public function cate(Request $request)
    {
        $this->getPage($request);
		$get = $request->get();
		$query = GoodsModel::query();
		if(!empty($get['id'])) {
			$ids = explode(',', $get['id']);
			$type = array_values(array_filter($ids));
			$query->where('type', 'LIKE', '%,'.implode(',',$type).',%');
		} else {
			return $this->dataJson(['list' => null]);
		}
        $goodsList = $query->orderBy('id', 'desc')->offset($this->getOffset())->limit($this->limit)->get();
        if(empty($goodsList)) {
            return $this->dataJson(['list' => null]);
        }
        $brandId = array();
        foreach($goodsList as $goods) {
            if(!in_array($goods['brand'], $brandId)) {
                $brandId[] = $goods['brand'];
            }
        }
        $brandList = BrandModel::query()->whereIn('id', $brandId)->get();
        $brandList = array_column($brandList->toArray(), null, 'id');
        foreach($goodsList as $key => $goods) {
            $brandGoods = $brandList[$goods['brand']] ?? [
            	'name_chi' => '',
            	'name_en' => ''
            ];
			$goodsList[$key]['images'] = empty($goods['files_path']) ? null : 'https://img.goods.acghx.net/'.$goods['files_path'];
            $goodsList[$key]['brand'] = [
                'id' => $goods['brand'],
                'name' => $brandGoods['name_chi'],
                'english' => $brandGoods['name_en']
            ];
        }
        return $this->dataJson(['list' => $goodsList,'search' => $this->keywords]);
    }

	public function rack(Request $request, string $gtin)
	{
		try {
            $data = Validator::input([
                'gtin' => $gtin
            ], [
                'gtin' => Validator::Digit()->NotEmpty()->setName('GTIN')
            ]);
            $info = GoodsModel::query()->where('gtin', '=', $data['gtin'])->firstOrFail();
			$rackList = GoodsRackInfoModel::query()->where('goods_id', '=', $info->id)->get();
			$racksReturn = [];
			if($rackList) {
				$racks = GoodRackModel::query()->join('shops', 'good_rack.shop_id', '=', 'shops.id')->whereIn('good_rack.id', $rackList->pluck('rack_id'))->get();
				foreach($racks->all() as $r) {
					$racksReturn[] = [
                        'name' => $r->name,
                        'shop' => [
                            'name_en' => $r->name_en
						],
						'images' => 'https://img.goods.acghx.net/'.$r->images
                    ];
				}
			}
			return $this->dataJson(['list' => $racksReturn]);
		} catch (ValidationException $e) {
            return $this->messageJson(404, 'Goods not found');
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
	}


    public function info(Request $request, string $gtin)
    {
        try {
            $data = Validator::input([
                'gtin' => $gtin
            ], [
                'gtin' => Validator::Digit()->NotEmpty()->setName('GTIN')
            ]);
            $info = GoodsModel::query()->where('gtin', '=', $data['gtin'])->firstOrFail();
            $brand = BrandModel::query()->findOrFail($info->brand);
            if($info->type) {
            	$listtype = array_values(array_filter(explode(',', $info->type)));
            	$listtype[0] = intval($listtype[0] ?? 0);
            	$listtype[1] = intval($listtype[1] ?? 0);
            	$typeNameList = TypesModel::query()->where('id', '=', $listtype[0])->orWhere('id', '=', $listtype[1])->get();
            	$typeName = [];
            	foreach($typeNameList as $name) {
            		$typeName[$name['level']] = $name['name'].'('.$name['en_name'].')';
            	}

            	$info->type = $listtype;
            } else {
            	$typeName = [];
            }
			
            return $this->dataJson([
                'id' => $info->id,
                'name' => $info->name_chi,
                'name_en' => $info->name_en,
                'gtin' => $info->gtin,
                'desc' => $info->descText_chi,
				'desc_en' => $info->descText_en,
				'specs' => $info->specs,
				'country' => $info->country,
                'brand' => [
                	'id' => $brand->id,
                    'name' => $brand->name_chi,
                    'english' => $brand->name_en,
					'country' => $brand->country,
                ],
                'type' => $info->type,
                'typeName' => implode('/', $typeName),
                'prices_low' => $info->low_price,
                'prices_high' => $info->high_price,
                'update_time' => $info->updated_at,
				'images' => empty($info->files_path) ? null : 'https://img.goods.acghx.net/'.$info->files_path,
				'files_id' => $info->files_id,
				'store_image' => empty($info->store_files_path) ? null : 'https://img.goods.acghx.net/'.$info->store_files_path,
				'store_files_id' => $info->store_files_id
            ]);
        } catch (ValidationException $e) {
            return $this->messageJson(404, 'Goods not found');
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
    }

    public function infoEdit(Request $request, int $id)
    {
        try {
            $post = $request->post();
            $post['brand'] = intval($post['brand'] ?? 0);
			$post['files_id'] = intval($post['files_id'] ?? 0);
			$post['store_files_id'] = intval($post['store_files_id'] ?? 0);
            $data = Validator::input($post, [
            	'type' => Validator::stringType()->NotEmpty()->setName('Good type id'),
                'name' => Validator::alwaysValid()->setName('Good zh-tw name'),
                'name_en' => Validator::alwaysValid()->setName('Good en name'),
                'desc' => Validator::alwaysValid()->setName('Good desc info'),
				'desc_en' => Validator::alwaysValid()->setName('Good desc info'),
                'brand' => Validator::NotEmpty()->setName('Brand Id'),
                'gtin' => Validator::Digit()->NotEmpty()->setName('GTIN'),
				'specs' => Validator::alwaysValid()->setName('Good specs info'),
				'files_id' => Validator::Number()->setName('Files Id'),
				'store_files_id' => Validator::Number()->setName('Files Id')
            ]);
            $data['country'] = $post['country'] ?? null;
			if(empty($data['name']) && empty($data['name_en'])) {
				$data['name_en'] = $data['gtin'];
			}
            $brand = BrandModel::query()->findOrFail(intval($data['brand']));
            $info = GoodsModel::query()->findOrFail(intval($id));
			if(intval($data['files_id'])>0) {
				$filesInfo = FilesModel::query()->findOrFail(intval($data['files_id']));
			} else {
				$filesInfo = null;
			}
			if(isset($data['store_files_id']) && intval($data['store_files_id'])>0) {
				$filesStoreInfo = FilesModel::query()->findOrFail(intval($data['store_files_id']));
			} else {
				$filesStoreInfo = null;
			}
            $info->name_chi = $data['name'];
			$info->files_id = intval($data['files_id']) ?? 0;
			$info->files_path = $filesInfo['files_path'] ?? '';
			$info->store_files_id = $data['store_files_id'] ?? 0;
			$info->store_files_path = $filesStoreInfo['files_path'] ?? '';
            $info->name_en = $data['name_en'];
            $info->descText_chi = $data['desc'];
			$info->descText_en = $data['desc_en'];
			$info->gtin = $data['gtin'];
            $info->specs = $data['specs'];
            $info->brand = $brand->id;
			if(ItemBarcode::ean13($data['gtin']) == false) {
				$info->country = $data['country'];
			} else {
				$info->country = ItemBarcode::ean13($data['gtin']);
			}
			$info->type = $data['type'] ?? '';
            $info->save();
			if($filesInfo) {
				$filesInfo->use_id = $info->id;
				$filesInfo->use_type = 'goods';
				$filesInfo->save();
			}
			if($filesStoreInfo) {
				$filesStoreInfo->use_id = $info->id;
				$filesStoreInfo->use_type = 'store_pic';
				$filesStoreInfo->save();
			}
            return $this->dataJson(['id' => $info->id]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
    }

    public function infoPost(Request $request, string $gtin)
    {
        try {
            $post = $request->post();
            $post['brand'] = intval($post['brand'] ?? 0);
            $post['brand'] = $post['brand'] < 1 ? 2 : $post['brand'];
            $post['gtin'] = $gtin;
			$post['files_id'] = intval($post['files_id'] ?? 0);
			$post['store_files_id'] = intval($post['store_files_id'] ?? 0);
            $data = Validator::input($post, [
            	'type' => Validator::stringType()->NotEmpty()->setName('Good type id'),
                'name' => Validator::alwaysValid()->setName('Good zh-tw name'),
                'name_en' => Validator::alwaysValid()->setName('Good en name'),
                'desc' => Validator::alwaysValid()->setName('Good desc info'),
				'desc_en' => Validator::alwaysValid()->setName('Good desc info'),
                'brand' => Validator::IntType()->NotEmpty()->setName('Brand Id'),
                'gtin' => Validator::Digit()->NotEmpty()->setName('GTIN'),
				'specs' => Validator::alwaysValid()->setName('Good specs info'),
				'files_id' => Validator::Number()->setName('Files Id'),
				'store_files_id' => Validator::Number()->setName('Files Id')
            ]);

			if(empty($data['name']) && empty($data['name_en'])) {
				$data['name_en'] = $data['gtin'];
			}
            $brand = BrandModel::query()->findOrFail(intval($data['brand']));
            $checkSn = GoodsModel::query()->where('gtin', '=', $data['gtin'])->first();
            if($checkSn) {
                return $this->messageJson(403, 'goods gtin isset', [
					'id' => $checkSn->id
				]);
            }
			if(intval($data['files_id'])>0) {
				$filesInfo = FilesModel::query()->findOrFail(intval($data['files_id']));
			} else {
				$filesInfo = null;
			}
			if(isset($data['store_files_id']) && intval($data['store_files_id'])>0) {
				$filesStoreInfo = FilesModel::query()->findOrFail(intval($data['store_files_id']));
			} else {
				$filesStoreInfo = null;
			}
			$data['country'] = $post['country'] ?? null;
            $info = new GoodsModel;
			$info->files_id = intval($data['files_id']) ?? 0;
			$info->files_path = $filesInfo['files_path'] ?? '';
			$info->store_files_id = $data['store_files_id'] ?? 0;
			$info->store_files_path = $filesStoreInfo['files_path'] ?? '';
            $info->name_chi = $data['name'];
            $info->name_en = $data['name_en'];
            $info->descText_chi = $data['desc'];
			$info->descText_en = $data['desc_en'];
			$info->specs = $data['specs'];
            $info->gtin = $data['gtin'];
            $info->brand = $brand->id;
			if(ItemBarcode::ean13($data['gtin']) == false) {
				$info->country = $data['country'];
			} else {
				$info->country = ItemBarcode::ean13($data['gtin']);
			}
			$info->sync_id = $data['sync_id'] ?? '';
			$info->type = $data['type'] ?? '';
            $info->save();
			if($filesInfo) {
				$filesInfo->use_id = $info->id;
				$filesInfo->use_type = 'goods';
				$filesInfo->save();
			}
			if($filesStoreInfo) {
				$filesStoreInfo->use_id = $info->id;
				$filesStoreInfo->use_type = 'store_pic';
				$filesStoreInfo->save();
			}
            return $this->dataJson(['id' => $info->id]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        }
    }
    
}
