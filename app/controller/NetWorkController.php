<?php
namespace app\controller;

use Illuminate\Database\QueryException;
use support\Request;
use support\Controller;

use Respect\Validation\Validator;
use Respect\Validation\Exceptions\ValidationException;
use Workerman\Http\Client as DownloadClient;

use app\model\{NetRex, NetRir as NetRirModel, NetRirLog as NetRirLogModel, NetRex as NetRexModel};
use app\helpers\{Ipv6,Ipv4};
use Exception;

class NetWorkController extends Controller
{
    private array $version = [];
    private string $registry = '';
    private string $type = '';

    public function sync(Request $request)
    {
        $post = $request->post();
        try {
            $data = Validator::input([
                'url' => $post['url'],
                'type' => $post['type'],
                'registry' => $post['registry']
            ], [
                'url' => Validator::Url()->NotEmpty()->setName('Sync Url'),
                'type' => Validator::Alpha()->NotEmpty()->setName('Type'),
                'registry' => Validator::Alpha()->setName('Registry'),
            ]);
            if($data['type'] == 'delegated') {
                $parts = explode('-', basename($data['url']));
                if(count($parts) < 3) {
                    return $this->messageJson(403, 'invalid delegated filename');
                }
                list($type, $registry, $date) = $parts;
            } else {
                $registry = $data['registry'] ?? '';
                if($data['type'] == 'rex') {
                    NetRex::query()->where('country', strtolower($registry))->delete();
                }
            }
            $logId = 0;
            $logs = NetRirLogModel::query()->where([
                'url' => $data['url'],
                'status' => 0
            ])->firstOrNew();
            $this->type = $data['type'];
            if ($logs->exists == true) {
                return $this->messageJson(403, 'the mission is on the road');
            } else {
                $logs->url = $data['url'];
                $logs->type = $data['type'];
                $logs->registry = $registry;
                $logs->save();
            }
            $logId = $logs->id;
            try {
                (new DownloadClient())->get($post['url'], function($response) use($logId) {
                    if($response->getStatusCode() == 200) {
                        try {
                            switch ($this->type) {
                                case 'delegated':
                                    $this->typeRirHandle($response);
                                    break;
                                case 'rex':
                                    $this->typeRex($response);
                                    break;
								case 'awsbgp':
									$this->typeAwsBgp($response);
									break;
                            }
                            $logs = NetRirLogModel::find($logId);
                            $logs->status = 1;
                            $logs->save();
                        } catch (Exception $e) {
                            $logs = NetRirLogModel::find($logId);
                            $logs->status = 3;
                            $logs->save();
                        }

                    }
                }, function($exception) use($logId) {
                    $logs = NetRirLogModel::find($logId);
                    $logs->status = 2;
                    $logs->save();
                });
            } catch (Exception $e) {
                $logs = NetRirLogModel::find($logId);
                $logs->status = 5;
                $logs->save();
            }
            return $this->dataJson([
                'fileCC' => $registry,
                'work' => $this->type
            ]);
        } catch (ValidationException $e) {
            return $this->messageJson(403, $e->getMessage());
        } catch (Exception $e) {
            return $this->messageJson(500, $e->getMessage().'/'.$e->getLine().'/'.$e->getFile());
        }
    }
	
	private function typeAwsBgp($response): bool
	{
		
	}

    private function typeRex($response): bool
    {
        $list = json_decode($response->getBody(), true);
        try {
            foreach($list['data']['items'] as $key => $line) {
                    if($line['allocation_type'] == 'ipv6' || $line['allocation_type'] == 'ipv4') {
                        if($line['allocation_type'] == 'ipv6') {
                            $res = (new Ipv6())->getAddressRange($line['allocation_address'], $line['length']);
                        } else {
                            $res = (new Ipv4())->getAddressRange($line['allocation_address'], $line['length']);
                        }
                        $line['allocation_address'] = ip2long_int($line['allocation_address']);
                        $line['allocation_start'] = ip2long_int($res['start_address']);
                        $line['allocation_end'] = ip2long_int($res['end_address']);
                    }
                    NetRexModel::query()->create($line);
            }
        } catch (Exception $e) {
            file_put_contents('debug.log', var_export([
                $e->getMessage(),
                $e->getFile(),
                $e->getCode(),
                $e->getLine()
            ], true));
        }
        return true;
    }

    private function typeRirHandle($response): bool
    {
        foreach($this->textLoad($response->getBody()) as $line) {
            $lineInfo = explode('|', $line);
            switch (count($lineInfo)) {
                case 7:
                    if (is_numeric($lineInfo[0])) {
                        $this->registry = $lineInfo[1];
                        $this->version = [
                            'val' => $lineInfo[0],
                            'records' => $lineInfo[3],
                            'serial' => $lineInfo[2]
                        ];
                    } else {
                        if ($lineInfo[1] !== '*' && $lineInfo[2] !== 'asn') {
                            $start = $end = '';
                            if($lineInfo[2] == 'ipv6') {
                                $res = (new Ipv6())->getAddressRange($lineInfo[3], $lineInfo[4]);
                            } else {
                                $res = (new Ipv4())->getAddressRange($lineInfo[3], $lineInfo[4]);
                            }
                            $start = $res['start_address'];
                            $end = $res['end_address'];
                            $info = new NetRirModel();
                            $info->hashcode = makeHashId($lineInfo[3].$lineInfo[1]);
                            $info->registry = $this->registry;
                            $info->version = $this->version['val'];
                            $info->serial = $this->version['serial'];
                            $info->cc = $lineInfo[1];
                            $info->type = $lineInfo[2];
                            $info->start = ip2long_int($start);
                            $info->end = ip2long_int($end);
                            $info->netrange = $lineInfo[4];
                            $info->date = $lineInfo[5];
                            $info->status = $lineInfo[6];
                            $info->extensions = $lineInfo[7] ?? '';
                            $info->save();
                        }
                    }
                    break;
            }
        }
        return true;
    }


    private function textLoad(string $body) {
        foreach(explode("\n", $body) as $val) {
            yield $val;
        }
    }
    
}
