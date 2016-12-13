<?php
use Tencentyun\ImageV2;

define('THUMB_WIDTH', 300);
define('THUMB_HEIGHT', 300);
define('THUMB_QUALITY', 85);

class ComuploadApp extends StoreadminbaseApp { //   StoreadminbaseApp  MemberbaseApp
    var $id = 0;
    var $belong = 0;
    var $store_id = 0;
    var $instance = null; //同一个模型可以设置多个不同实例（goods模型可以有商品相册或商品描述两个实例）

    function __construct() {
        $this->ComuploadApp();
    }

    function ComuploadApp() {
        parent::__construct();
        if ( isset( $_REQUEST['id'] ) ) {
            $this->id = intval($_REQUEST['id']);
        }
        if ( isset( $_REQUEST['belong'] ) ) {
            $this->belong = intval($_REQUEST['belong']);
        }
        /* 实例 */
        if ( isset( $_REQUEST['instance'] ) ) {
            $this->instance = $_REQUEST['instance'];
        }
        $this->store_id = $this->visitor->get('manage_store');

    }

    /**
     * 艺加上传文件 用于微信
     *
     * by Gavin 20161122
     */
    public function ejUploadFile4Wechat() {
        // 文件上传模型
        $upload_mod =& m('uploadedfile');

        /* 取得剩余空间（单位：字节），false表示不限制 */
        $store_mod =& m('store');
        $settings = $store_mod->get_settings($this->store_id);
        // 还可上传大小 单位 B(字节)
        $remain = $settings['space_limit'] > 0 ? $settings['space_limit'] * 1024 * 1024 - $upload_mod->get_file_size($this->store_id) : false;

        $mediaIDS = $_REQUEST['mediaID'] ? explode(',',$_REQUEST['mediaID']) : '' ;

        if ( is_array($mediaIDS) ) {
            $uploaded = [];

            $temporaryHandler = Wechat::handler()->material_temporary;
            $fileDir = '/tmp/wechat/';
            if( !is_dir($fileDir) && !mkdir($fileDir,0700,true)){
                return $this->ej_json_failed(-1, '目录创建失败');
            }

            foreach ($mediaIDS as $mediaID){
                if(empty($mediaID)) continue; // mediaID 为空,跳出当前继续执行

                // 从微信下载文件 放到临时目录
                $fileName = $temporaryHandler->download($mediaID, $fileDir);
                $fileUrl = $fileDir . $fileName;

                // 必须是图片
                $mimeType = image_type_to_mime_type(exif_imagetype($fileUrl));
                if(!in_array($mimeType,['image/gif','image/jpeg','image/png','image/bmp'])){
                    $uploaded[$mediaID] = [
                        'code' => -1,
                        'desc' => '图片类型不正确',
                    ];
                    continue;
                }

                // 正常文件 && 用户可用空间足够
                if ( is_file($fileUrl) && $remain !== false && $remain < filesize($fileUrl) ) {
                    $uploaded[$mediaID] = [
                        'code' => -1,
                        'desc' => '很抱歉，您上传的文件所占空间已达上限，请联系商城管理员升级店铺',
                    ];
                    continue;
                }

                // 从临时目录拿到文件 上传到万象优图
                $cloudRetArr = ImageV2::upload($fileUrl, CLOUD_IMAGE_BUCKET);
                if ( $cloudRetArr['httpcode'] != 200 ) {
                    $uploaded[$mediaID] = [
                        'code' => -1,
                        'desc' => '上传服务器错误',
                    ];
                    continue;
                }

                /* 数据库保存 */
                $data = [
                    'store_id'  => $this->store_id,
                    'file_type' => $mimeType,
                    'file_size' => filesize($fileUrl),
                    'file_name' => $cloudRetArr['data']['fileid'],
                    'file_path' => $cloudRetArr['data']['downloadUrl'],
                    'cloud_image_id' => $cloudRetArr['data']['fileid'],
                    'cloud_image_data' => json_encode($cloudRetArr),
                    'belong'    => $this->belong,
                    'item_id'   => $this->id,
                    'add_time'  => gmtime(),
                ];
                $file_id = $upload_mod->add($data);
                if ( !$file_id ) {
                    $uploaded[$mediaID] = [
                        'code' => -1,
                        'desc' => '上传保存出错',
                    ];
                    continue;
                }

                // 如果是上传商品相册图片
                if ( $this->instance == 'goods_image' ) {
                    /* 更新商品相册 */
                    $mod_goods_image = &m('goodsimage');
                    $goods_image = [
                        'goods_id'   => $this->id,
                        'image_url'  => $cloudRetArr['data']['downloadUrl'],
                        'thumbnail'  => $cloudRetArr['data']['downloadUrl'],
                        'sort_order' => 255,
                        'file_id'    => $file_id,
                    ];
                    if ( !$mod_goods_image->add($goods_image) ) {
                        $uploaded[$mediaID] = [
                            'code' => -1,
                            'desc' => '保存商品图片错误',
                        ];
                        continue;
                    }
                }

                $data['instance'] = $this->instance;
                $data['file_id'] = $file_id;

                // 上传成功 合并数组  返回数据
                $uploaded[$mediaID] = [
                    'code' => 0,
                    'desc' => 'ok'
                ];
                $uploaded[$mediaID] = array_merge($uploaded[$mediaID],$data);
            }

            return $this->ej_json_success($uploaded);
        } else {
            return $this->ej_json_failed(2001);
        }

    }

    public function test(){
        return $this->ej_json_failed(-1);
    }

    /**
     * 艺加 - 上传图片
     *
     * @return bool
     */
    function ejUploadedFile() {
        import('image.func');
        import('uploader.lib');
        $uploader = new Uploader();
        $uploader->allowed_type(IMAGE_FILE_TYPE);
        $uploader->allowed_size(SIZE_GOODS_IMAGE); // 2M
        $upload_mod =& m('uploadedfile');
        /* 取得剩余空间（单位：字节），false表示不限制 */
        $store_mod =& m('store');
        $settings = $store_mod->get_settings($this->store_id);

        $remain = $settings['space_limit'] > 0 ? $settings['space_limit'] * 1024 * 1024 - $upload_mod->get_file_size($this->store_id) : false;

        $files = $_FILES['file'];
        if ( $files['error'] === UPLOAD_ERR_OK ) {
            /* 处理文件上传 */
            $file = [
                'name'     => $files['name'],
                'type'     => $files['type'],
                'tmp_name' => $files['tmp_name'],
                'size'     => $files['size'],
                'error'    => $files['error']
            ];
            $uploader->addFile($file);
            if ( !$uploader->file_info() ) {
                $data = current($uploader->get_error());
                $res = Lang::get($data['msg']);

                return $this->ej_json_failed(-1, $res);
            }
            /* 判断能否上传 */
            if ( $remain !== false ) {
                if ( $remain < $file['size'] ) {
                    $res = Lang::get('space_limit_arrived');

                    return $this->ej_json_failed(-1, $res);
                }
            }

            $uploader->root_dir(ROOT_PATH);
            $dirname = '';
            if ( $this->belong == BELONG_GOODS ) {
                $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/goods_' . ( time() % 200 );
            } elseif ( $this->belong == BELONG_STORE ) {
                $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/other';
            } elseif ( $this->belong == BELONG_ARTICLE ) {
                $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/article';
            }

            $filename = $uploader->random_filename();
            $file_path = $uploader->save($dirname, $filename);
            /* 处理文件入库 */
            $data = [
                'store_id'  => $this->store_id,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
                'file_name' => $file['name'],
                'file_path' => $file_path,
                'belong'    => $this->belong,
                'item_id'   => $this->id,
                'add_time'  => gmtime(),
            ];
            $file_id = $upload_mod->add($data);
            if ( !$file_id ) {
                $this->_error($uf_mod->get_error());

                return $this->ej_json_failed(-1);
            }

            if ( $this->instance == 'goods_image' ) // 如果是上传商品相册图片
            {
                /* 生成缩略图 */
                $thumbnail = dirname($file_path) . '/small_' . basename($file_path);
                make_thumb(ROOT_PATH . '/' . $file_path, ROOT_PATH . '/' . $thumbnail, THUMB_WIDTH, THUMB_HEIGHT, THUMB_QUALITY);

                /* 更新商品相册 */
                $mod_goods_image = &m('goodsimage');
                $goods_image = [
                    'goods_id'   => $this->id,
                    'image_url'  => $file_path,
                    'thumbnail'  => $thumbnail,
                    'sort_order' => 255,
                    'file_id'    => $file_id,
                ];
                if ( !$mod_goods_image->add($goods_image) ) {
                    $this->_error($this->mod_goods_imaged->get_error());

                    return $this->ej_json_failed(-1, $res);
                }

                $data['thumbnail'] = $thumbnail;
            }

            $data['instance'] = $this->instance;
            $data['file_id'] = $file_id;

            return $this->ej_json_success($data);
        } elseif ( $files['error'] == UPLOAD_ERR_NO_FILE ) {
            $res = Lang::get('file_empty');

            return $this->ej_json_failed(-1, $res);
        } else {
            $res = Lang::get('sys_error');

            return $this->ej_json_failed(-1, $res);
        }

    }

    function view_iframe() {
        $this->assign("act", 'uploadedfile');
        $this->assign("id", $this->id);
        $this->assign("instance", $this->instance);
        $this->assign("belong", $this->belong);
        $this->display("image.html");
    }

    function uploadedfile() {
        import('image.func');
        import('uploader.lib');
        $uploader = new Uploader();
        $uploader->allowed_type(IMAGE_FILE_TYPE);
        $uploader->allowed_size(SIZE_GOODS_IMAGE); // 2M
        $upload_mod =& m('uploadedfile');
        /* 取得剩余空间（单位：字节），false表示不限制 */
        $store_mod =& m('store');
        $settings = $store_mod->get_settings($this->store_id);

        $remain = $settings['space_limit'] > 0 ? $settings['space_limit'] * 1024 * 1024 - $upload_mod->get_file_size($this->store_id) : false;

        $files = $_FILES['file'];
        if ( $files['error'] === UPLOAD_ERR_OK ) {
            /* 处理文件上传 */
            $file = [
                'name'     => $files['name'],
                'type'     => $files['type'],
                'tmp_name' => $files['tmp_name'],
                'size'     => $files['size'],
                'error'    => $files['error']
            ];
            $uploader->addFile($file);
            if ( !$uploader->file_info() ) {
                $data = current($uploader->get_error());
                $res = Lang::get($data['msg']);
                $this->view_iframe();
                echo "<script type='text/javascript'>alert('{$res}');</script>";

                return false;
            }
            /* 判断能否上传 */
            if ( $remain !== false ) {
                if ( $remain < $file['size'] ) {
                    $res = Lang::get('space_limit_arrived');
                    $this->view_iframe();
                    echo "<script type='text/javascript'>alert('{$res}');</script>";

                    return false;
                }
            }

            $uploader->root_dir(ROOT_PATH);
            $dirname = '';
            if ( $this->belong == BELONG_GOODS ) {
                $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/goods_' . ( time() % 200 );
            } elseif ( $this->belong == BELONG_STORE ) {
                $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/other';
            } elseif ( $this->belong == BELONG_ARTICLE ) {
                $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/article';
            }

            $filename = $uploader->random_filename();
            $file_path = $uploader->save($dirname, $filename);
            /* 处理文件入库 */
            $data = [
                'store_id'  => $this->store_id,
                'file_type' => $file['type'],
                'file_size' => $file['size'],
                'file_name' => $file['name'],
                'file_path' => $file_path,
                'belong'    => $this->belong,
                'item_id'   => $this->id,
                'add_time'  => gmtime(),
            ];
            $file_id = $upload_mod->add($data);
            if ( !$file_id ) {
                $this->_error($uf_mod->get_error());

                return false;
            }

            if ( $this->instance == 'goods_image' ) // 如果是上传商品相册图片
            {
                /* 生成缩略图 */
                $thumbnail = dirname($file_path) . '/small_' . basename($file_path);
                make_thumb(ROOT_PATH . '/' . $file_path, ROOT_PATH . '/' . $thumbnail, THUMB_WIDTH, THUMB_HEIGHT, THUMB_QUALITY);

                /* 更新商品相册 */
                $mod_goods_image = &m('goodsimage');
                $goods_image = [
                    'goods_id'   => $this->id,
                    'image_url'  => $file_path,
                    'thumbnail'  => $thumbnail,
                    'sort_order' => 255,
                    'file_id'    => $file_id,
                ];
                if ( !$mod_goods_image->add($goods_image) ) {
                    $this->_error($this->mod_goods_imaged->get_error());

                    return false;
                }
                $data['thumbnail'] = $thumbnail;

            }

            $data['instance'] = $this->instance;
            $data['file_id'] = $file_id;
            $res = "{";
            foreach ( $data as $key => $val ) {
                $res .= "\"$key\":\"$val\",";
            }
            $res = substr($res, 0, strrpos($res, ','));
            $res .= '}';
            $this->view_iframe();
            echo "<script type='text/javascript'>window.parent.add_uploadedfile($res);</script>";
        } elseif ( $files['error'] == UPLOAD_ERR_NO_FILE ) {
            $res = Lang::get('file_empty');
            $this->view_iframe();
            echo "<script type='text/javascript'>alert('{$res}');</script>";

            return false;
        } else {
            $res = Lang::get('sys_error');
            $this->view_iframe();
            echo "<script type='text/javascript'>alert('{$res}');</script>";

            return false;
        }

    }

    function view_remote() {
        $this->assign("act", 'remote_image');
        $this->assign("instance", $this->instance);
        $this->assign("id", $this->id);
        $this->assign("belong", $this->belong);
        $this->display("image.html");
    }

    function remote_image() {
        import('image.func');
        import('uploader.lib');
        $uploader = new Uploader();
        $uploader->allowed_type(IMAGE_FILE_TYPE);
        $uploader->allowed_size(SIZE_GOODS_IMAGE); // 2M
        $upload_mod =& m('uploadedfile');
        /* 取得剩余空间（单位：字节），false表示不限制 */
        $store_mod =& m('store');
        $settings = $store_mod->get_settings($this->store_id);
        $remain = $settings['space_limit'] > 0 ? $settings['space_limit'] * 1024 * 1024 - $upload_mod->get_file_size($this->store_id) : false;
        $uploader->root_dir(ROOT_PATH);
        $dirname = '';
        $remote_url = trim($_POST['remote_url']);
        if ( !empty( $remote_url ) ) {
            if ( preg_match("/^(http:\/\/){1,1}.+(gif|png|jpeg|jpg){1,1}$/i", $remote_url) ) {
                $result = $this->url_exist($remote_url, 2097152, $remain);
                if ( $result === 1 ) {
                    $this->view_remote();
                    $res = Lang::get("url_invalid");
                    echo "<script type='text/javascript'>alert('{$res}');</script>";

                    return false;
                } elseif ( $result === 2 ) {
                    $this->view_remote();
                    $res = Lang::get("not_allowed_size");
                    echo "<script type='text/javascript'>alert('{$res}');</script>";

                    return false;
                } elseif ( $result === 3 ) {
                    $this->view_remote();
                    $res = Lang::get("space_limit_arrived");
                    echo "<script type='text/javascript'>alert('{$res}');</script>";

                    return false;
                }
                $img_url = _at('file_get_contents', $remote_url);
                $dirname = '';
                if ( $this->belong == BELONG_GOODS ) {
                    $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/goods_' . ( time() % 200 );
                } elseif ( $this->belong == BELONG_STORE ) {
                    $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/other';
                } elseif ( $this->belong == BELONG_ARTICLE ) {
                    $dirname = 'data/files/store_' . $this->visitor->get('manage_store') . '/article';
                }
                $filename = $uploader->random_filename();
                $new_url = $dirname . '/' . $filename . '.' . substr($remote_url, strrpos($remote_url, '.') + 1);
                ecm_mkdir(ROOT_PATH . '/' . $dirname);
                $fp = _at('fopen', ROOT_PATH . '/' . $new_url, "w");
                _at('fwrite', $fp, $img_url);
                _at('fclose', $fp);
                if ( !file_exists(ROOT_PATH . '/' . $new_url) ) {
                    $this->view_remote();
                    $res = Lang::get("system_error");
                    echo "<script type='text/javascript'>alert('{$res}');</script>";

                    return false;
                }
                /* 处理文件入库 */
                $data = [
                    'store_id'  => $this->store_id,
                    'file_type' => $this->_return_mimetype(ROOT_PATH . '/' . $new_url),
                    'file_size' => filesize(ROOT_PATH . '/' . $new_url),
                    'file_name' => substr($remote_url, strrpos($remote_url, '/') + 1),
                    'file_path' => $new_url,
                    'belong'    => $this->belong,
                    'item_id'   => $this->id,
                    'add_time'  => gmtime(),
                ];
                $file_id = $upload_mod->add($data);
                if ( !$file_id ) {
                    $this->_error($uf_mod->get_error());

                    return false;
                }

                if ( $this->instance == 'goods_image' ) // 如果是上传商品相册图片
                {
                    /* 生成缩略图 */
                    $thumbnail = dirname($new_url) . '/small_' . basename($new_url);
                    make_thumb(ROOT_PATH . '/' . $new_url, ROOT_PATH . '/' . $thumbnail, THUMB_WIDTH, THUMB_HEIGHT, THUMB_QUALITY);

                    /* 更新商品相册 */
                    $mod_goods_image = &m('goodsimage');
                    $goods_image = [
                        'goods_id'   => $this->id,
                        'image_url'  => $new_url,
                        'thumbnail'  => $thumbnail,
                        'sort_order' => 255,
                        'file_id'    => $file_id,
                    ];
                    if ( !$mod_goods_image->add($goods_image) ) {
                        $this->_error($this->mod_goods_imaged->get_error());

                        return false;
                    }
                    $data['thumbnail'] = $thumbnail;

                }

                $data['instance'] = $this->instance;
                $data['file_id'] = $file_id;
                $res = "{";
                foreach ( $data as $key => $val ) {
                    $res .= "\"$key\":\"$val\",";
                }
                $res = substr($res, 0, strrpos($res, ','));
                $res .= '}';
                $this->view_remote();
                echo "<script type='text/javascript'>window.parent.add_uploadedfile($res);</script>";
            } else {
                $res = Lang::get('url_invalid');
                $this->view_remote();

                echo "<script type='text/javascript'>alert('{$res}');</script>";

                return false;
            }
        } else {
            $res = Lang::get('remote_empty');
            $this->view_remote();
            echo "<script type='text/javascript'>alert('{$res}');</script>";

            return false;
        }
    }

    /**
     * 检查远程地址是否有效，文件是否超过最大允许值，剩余空间是否够用
     *
     * @author cheng
     *
     * @param string $url        | 远程地址
     * @param int    $allow_size | 允许上传文件的最大值
     * @param int    $remain     | 用户剩余的空间
     *                           0 | 不用检查，无限大
     *
     * @return int 1 | 无效的远程地址
     *         int 2 | 文件大小超过允许值
     *         int 3 | 文件大小超过剩余空间的大小
     *         boolen true | 通过检测
     */
    function url_exist( $url, $allow_size, $remain ) {
        if ( !function_exists('get_headers') ) {
            function get_headers( $url, $format = 0 ) {
                $url = parse_url($url);
                $end = "\r\n\r\n";
                $fp = fsockopen($url['host'], ( empty( $url['port'] ) ? 80 : $url['port'] ), $errno, $errstr, 30);
                if ( $fp ) {
                    $out = "GET " . $url['path'] . " HTTP/1.1\r\n";
                    $out .= "Host: " . $url['host'] . "\r\n";
                    $out .= "Connection: Close\r\n\r\n";
                    $var = '';
                    fwrite($fp, $out);
                    while ( !feof($fp) ) {
                        $var .= fgets($fp, 1280);
                        if ( strpos($var, $end) )
                            break;
                    }
                    fclose($fp);

                    $var = preg_replace("/\r\n\r\n.*\$/", '', $var);
                    $var = explode("\r\n", $var);
                    if ( $format ) {
                        foreach ( $var as $i ) {
                            if ( preg_match('/^([a-zA-Z -]+): +(.*)$/', $i, $parts) )
                                $v[ $parts[1] ] = $parts[2];
                        }

                        return $v;
                    } else
                        return $var;
                }
            }
        }
        $head = get_headers($url);
        if ( is_array($head) && !empty( $head ) ) {
            foreach ( $head as $key => $val ) {
                //$val = str_replace("\r\n", '', $val);
                $pos = strpos($val, 'Content-Length');
                if ( $key == 0 ) {
                    $hhttp = explode(' ', $val);
                    $hsize = count($hhttp) - 1;
                    $res = strcmp($hhttp[ $hsize ], "OK");
                    if ( $res != 0 ) {
                        return 1;
                    }
                } elseif ( $pos === false ) {
                    continue;
                } elseif ( $pos >= 0 ) {
                    $size = explode(' ', $val);
                    $count = count($size);
                    $count = $count - 1;
                    $res = intval($size[ $count ]);
                    if ( $res > $allow_size ) {
                        return 2;
                    }
                    if ( !empty( $remain ) && $res > $remain ) {
                        return 3;
                    }
                }
            }
        } else {
            return 1;
        }

        return true;
    }

    function _return_mimetype( $filename ) {
        preg_match("|\.([a-z0-9]{2,4})$|i", $filename, $fileSuffix);
        switch ( strtolower($fileSuffix[1]) ) {
            case "js" :
                return "application/x-javascript";

            case "json" :
                return "application/json";

            case "jpg" :
            case "jpeg" :
            case "jpe" :
                return "image/jpeg";

            case "png" :
            case "gif" :
            case "bmp" :
            case "tiff" :
                return "image/" . strtolower($fileSuffix[1]);

            case "css" :
                return "text/css";

            case "xml" :
                return "application/xml";

            case "doc" :
            case "docx" :
                return "application/msword";

            case "xls" :
            case "xlt" :
            case "xlm" :
            case "xld" :
            case "xla" :
            case "xlc" :
            case "xlw" :
            case "xll" :
                return "application/vnd.ms-excel";

            case "ppt" :
            case "pps" :
                return "application/vnd.ms-powerpoint";

            case "rtf" :
                return "application/rtf";

            case "pdf" :
                return "application/pdf";

            case "html" :
            case "htm" :
            case "php" :
                return "text/html";

            case "txt" :
                return "text/plain";

            case "mpeg" :
            case "mpg" :
            case "mpe" :
                return "video/mpeg";

            case "mp3" :
                return "audio/mpeg3";

            case "wav" :
                return "audio/wav";

            case "aiff" :
            case "aif" :
                return "audio/aiff";

            case "avi" :
                return "video/msvideo";

            case "wmv" :
                return "video/x-ms-wmv";

            case "mov" :
                return "video/quicktime";

            case "rar" :
                return "application/x-rar-compressed";

            case "zip" :
                return "application/zip";

            case "tar" :
                return "application/x-tar";

            case "swf" :
                return "application/x-shockwave-flash";

            default :
                if ( function_exists("mime_content_type") ) {
                    $fileSuffix = mime_content_type($filename);
                }

                return "unknown/" . trim($fileSuffix[0], ".");
        }
    }
}

?>