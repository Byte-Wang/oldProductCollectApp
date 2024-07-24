<?php

namespace app\common\library;


use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\exception\ValidateException;
use think\facade\Filesystem;

class Excel
{
    // excel导入
    public static function import($file)
    {
        try {
            // 验证文件大小，名称等是否正确
            validate(['file' => 'filesize:51200|fileExt:xls,xlsx'])
                ->check([$file]);
            // 将文件保存到本地
            $savename = Filesystem::putFile('topic', $file);
            // 读取文件，tp6默认上传的文件，在runtime的相应目录下，可根据实际情况自己更改
            $objPHPExcel = IOFactory::load(runtime_path() . '../storage/' . $savename);
            //excel中的第一张sheet
            $sheet = $objPHPExcel->getSheet(0);
            // 取得总行数
            $highestRow = $sheet->getHighestRow();
            // 取得总列数
            $highestColumn = $sheet->getHighestColumn();
            Coordinate::columnIndexFromString($highestColumn);
            $lines = $highestRow - 1;
            if ($lines <= 0) {
                echo('数据不能为空！');
                exit();
            }
            // 直接取出excle中的数据
            $data = $objPHPExcel->getActiveSheet()->toArray();
            // 删除第一个元素（表头）
            array_shift($data);
            // 返回结果
            return $data;
        } catch (ValidateException $e) {
            return $e->getMessage();
        }
    }

    // 导出
    //// 设置表格的表头数据
    //    $header = ["A1" => "编号", "B1" => "姓名", "C1" => "年龄"];
    //    // 假设下面这个数组从数据库查询出的二维数组
    //    $data = [
    //        [1,'某某某',18],
    //        [2,'某某某',19],
    //        [3,'某某某',22],
    //        [4,'某某某',19],
    //        [5,'某某某',29]
    //    ];
    //
    //————————————————
    //版权声明：本文为CSDN博主「蝶妹妹」的原创文章，遵循CC 4.0 BY-SA版权协议，转载请附上原文出处链接及本声明。
    //原文链接：https://blog.csdn.net/qq_58467694/article/details/121658519
    public static function export($header = [], $type = true, $data = [], $fileName = "")
    {
        $fileName .= date('YmdHis', time()) . rand(100, 999);
        $list = [];
        foreach ($data as $value) {
            $temp = [];
            foreach ($header as $k => $item) {
                $temp[] = $value[$k];
            }
            $list[] = $temp;
        }
        $title = [];
        $t = 'A';
        foreach ($header as $tit) {
            $title[$t . '1'] = $tit;
            $t++;
        }

        // 实例化类
        $preadsheet = new Spreadsheet();
        // 创建sheet
        $sheet = $preadsheet->getActiveSheet();
        foreach ($title as $k => $v) {
            $sheet->setCellValue($k, $v);
        }

        $sheet->fromArray($list, null, "A2");
        // 样式设置
        $sheet->getDefaultColumnDimension()->setWidth(12);
        // 设置下载与后缀
        if ($type) {
            header("Content-Type:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            $type = "Xlsx";
            $suffix = "xlsx";
        } else {
            header("Content-Type:application/vnd.ms-excel");
            $type = "Xls";
            $suffix = "xls";
        }
        // 激活浏览器窗口
        header("Content-Disposition:attachment;filename=$fileName.$suffix");
        //缓存控制
        header("Cache-Control:max-age=0");
        // 调用方法执行下载
        $writer = IOFactory::createWriter($preadsheet, $type);
        // 数据流
        $writer->save("php://output");
    }
}