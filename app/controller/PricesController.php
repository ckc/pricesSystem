<?php
namespace app\controller;

use support\Request;
use support\Controller;

use Respect\Validation\Validator;
use Respect\Validation\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use app\model\{Goods as GoodsModel, Prices as PricesModel, PricesLog as PricesLogModel};

class PricesController extends Controller
{

    public function goods(Request $request, string $gtin)
    {
    	try {
            $data = Validator::input([
                'gtin' => $gtin
            ], [
                'gtin' => Validator::Digit()->NotEmpty()->setName('GTIN')
            ]);
            $info = GoodsModel::query()->where('gtin', '=', $data['gtin'])->firstOrFail();
            $infoPrices = PricesModel::query()->selectRaw('prices.shop_id, prices.prices,max(prices.created_at) as created_at,max(prices.updated_at) as updated_at,prices.sku,shops.name_chi as shop_name_chi,shops.name_en as shop_name_en,shops.address_en as shop_address_en')->join('shops', 'prices.shop_id', '=', 'shops.id')->where('prices.goods_id', '=', $info->id)->groupByRaw('prices.shop_id, prices.prices, prices.sku, shops.name_chi, shops.name_en,shops.address_en')->orderByRaw('prices.sku desc, prices.updated_at desc')->get();
            $goodsPrices = array();
            foreach($infoPrices as $prices) {
            	$goodsPrices[] = array(
            		'shop_id' => $prices->shop_id,
					'shop_name' => $prices->shop_name_chi,
					'shop_address' => $prices->shop_address_en,
					'shop_name_english' => $prices->shop_name_en,
            		'prices' => $prices->prices,
            		'sku' => $prices->sku,
            		'create' => $prices->created_at,
            		'update' => $prices->updated_at,
            	);
            }
            return $this->dataJson([
                'id' => $info->id,
                'name' => $info->name,
                'name_en' => $info->english,
                'gtin' => $gtin,
                'prices' => $goodsPrices
            ]);
        } catch (ValidationException $e) {
            return $this->messageJson(404, 'Goods not found');
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
        return json(['code' => 0, 'msg' => 'ok']);
    }

    public function goodsPost(Request $request, int $id)
    {
    	try {
    		$post = $request->post();
    		$post['id'] = $id;
    		$post['shop_id'] = intval($post['shop_id'] ?? 0);
    		$post['prices'] = floatval($post['prices'] ?? 0);
			$post['sku'] = intval($post['sku'] ?? 0);
			if($post['sku'] < 1) {
				$post['sku'] = 1;
			}
            $data = Validator::input($post, [
                'id' => Validator::IntType()->NotEmpty()->setName('Good Id'),
                'shop_id' => Validator::IntType()->NotEmpty()->setName('Shop Id'),
                'sku' => Validator::IntType()->setName('SKU'),
                'prices' => Validator::FloatType()->NotEmpty()->setName('Prices')
            ]);
            if(empty($data['sku'])) {
            	$data['sku'] = 'default';
            }
			$infoGoods = GoodsModel::query()->findOrFail($data['id']);
            $info = PricesModel::query()->where('goods_id', '=', $data['id'])->where('shop_id', '=', $data['shop_id'])->where('sku', '=', $data['sku'])->first();
            $pricesId = 0;
            if(empty($info)) {
            	$infoData = new PricesModel;
            	$infoData->goods_id = $data['id'];
            	$infoData->shop_id = $data['shop_id'];
            	$infoData->prices = $data['prices'];
            	$infoData->sku = $data['sku'];
            	$infoData->save();
            	$pricesId = $infoData->id;
            } else {
				$pricesId = $info->id;
            	if(floatval($info->prices) == $data['prices']) {
            		return $this->dataJson(['id' => $pricesId, 'updated_at' => $info->updated_at]);
            	}
            	$info->prices = $data['prices'];
            	$info->save();
            }
			$maxPrices = PricesModel::query()->where('goods_id', '=', $data['id'])->max('prices');
			$minPrices = PricesModel::query()->where('goods_id', '=', $data['id'])->min('prices');
			$infoGoods->low_price = $minPrices;
			$infoGoods->high_price = $maxPrices;
			$infoGoods->save();
            return $this->dataJson(['id' => $pricesId, 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        } catch (ModelNotFoundException $e) {
            return json(['code' => 404, 'msg' => $e->getMessage()]);
        }
    }

}
