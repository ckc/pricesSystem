<?php
namespace process;

use Workerman\Crontab\Crontab;
use app\model\{Files as FilesModel};
class Task
{
    public function onWorkerStart()
    {

        // 每天6点执行，注意这里省略了秒位
        new Crontab('0 6 * * *', function() {
			$fileUnused = FilesModel::where('created_at', '<', date('Y-m-d H:i:s', time()-86400*2))->where('use_id', 0)->get();
			foreach($fileUnused as $files) {
				if(is_file(public_path().'/goods/'.$files['files_path'])) {
					unlink(public_path().'/goods/'.$files['files_path']);
				}
				FilesModel::where('id', '=', $files['id'])->delete();
			}
        });

    }
}