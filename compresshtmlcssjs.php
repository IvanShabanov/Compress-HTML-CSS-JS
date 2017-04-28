<?php
if(empty($_GET['handle'])) {
    ob_start ("compressHtmlCssJs");
}    

function Var_Dump_my($dump, $level = 0) {
  $result = '';
  if (is_array($dump)) {
   $result .= 'Array('."\n";
    $level++;
    foreach ($dump as $key=>$val) {
      $result .= str_repeat("\t", $level);
      $result .= '['.$key.'] => '.Var_Dump_my($val, $level);
    }
   $result .= "\n)\n";    
  } else {
    $result .= ''.$dump.''."\n";
  }
  return $result;
}

function compressLog($text) {
  if (is_array($text)) {
    $text = Var_Dump_my($text);
  }
  $compressed_dir = $_SERVER['DOCUMENT_ROOT'].'';
  $file = $compressed_dir.'/log.txt';  
  file_put_contents($file, "---------------------\n", FILE_APPEND);
  file_put_contents($file, date('Y-m-d H:i:s')."\t".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."\n", FILE_APPEND);
  file_put_contents($file, $text."\n", FILE_APPEND);
  file_put_contents($file, "---------------------\n", FILE_APPEND);

}

function compressCss($buffer) {
    $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
    $buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '  ', '   ' ,'    '), '', $buffer);
    return $buffer;
}

function findCompressCss($buffer) {

    /**********************************************/
    /**********************************************/
    /**********************************************/
    /* Сжатие CSS */
    preg_match_all ('/<link[^>]*href="(.*?)"[^>]*>/', $buffer, $output_link);
    foreach ($output_link[0] as $key=>$val ) {
      $need = false;
      /* Если это stylesheet то надо compress его */
      if (preg_match ('/.*rel.*=.*stylesheet.*/i' , $val)) {
        $need = true;
      }
      /* Исключим ссылки на сторонние сайты */
      if (preg_match ('/http.*/i' , $output_link[1][$key])) {
        if (!preg_match ('/http.*'.$_SERVER['HTTP_HOST'].'.*/i' , $output_link[1][$key])) {
          $need = false;
        } else {
          $output_link[1][$key] = str_replace ('https://'.$_SERVER['HTTP_HOST'], '' , $output_link[1][$key]);
          $output_link[1][$key] = str_replace ('http://'.$_SERVER['HTTP_HOST'], '' , $output_link[1][$key]);
        }
      }
      if ($need) {
        $file_css = $_SERVER['DOCUMENT_ROOT'].$output_link[1][$key];
        if (strpos($file_css, '?') > 0) {
          $file_css = substr($file_css, 0 ,strpos($file_css, '?'));
        }
        if (file_exists($file_css)) {
          $curinfo['size'] = filesize($file_css);
          $curinfo['time'] = filemtime($file_css);
          $file_css_new = str_replace('.css', '.compress.css', $file_css);
          $link_new = str_replace('.css', '.compress.css', $output_link[0][$key]);
          $info = array();
          if (file_exists($file_css_new)) {
            $css_old_compress = file_get_contents($file_css_new);
            $css_old_compress = substr($css_old_compress, strpos($css_old_compress,'-===') + 4);
            $css_old_compress = substr($css_old_compress, 0, strpos($css_old_compress, '===-'));
            $info = unserialize($css_old_compress);
          }
          if (($curinfo['size'] != $info['size']) or ($curinfo['time'] != $info['time']))  {
            file_put_contents($file_css_new, '/* !!! Оригинальный файл / Original file :'.$output_link[1][$key].' !!! Этот файл будет сгенерирован автоматически при изменении оригинала / This file will be automaticly update  -==='.serialize($curinfo).'===- */'.compressCss(file_get_contents($file_css)));
          }
          $buffer = str_replace($output_link[0][$key], $link_new, $buffer);
        }
      }
    }


    return $buffer;

}

function compressJS($buffer) {
/* Комментарии */
    $buffer = preg_replace('/(\/\/.*\n)/', '', $buffer);
    $buffer = preg_replace('/(\/\*([^(\*\/)]*)\*\/)/', '', $buffer);
/* Trim строк */
    $buffer = preg_replace('/(\n[\s]*)/', "\n", $buffer);
    $buffer = preg_replace('/([\s]*)\n/', "\n", $buffer);
    $buffer = str_replace(array(' =', '= '), "=", $buffer);

/*  убрать перевод строк где это можно делать */
    $buffer = preg_replace('/(;\n)/', ";", $buffer);
    $buffer = preg_replace('/(,\n)/', ", ", $buffer);
    $buffer = preg_replace('/({\n)/', "{", $buffer);
    $buffer = preg_replace('/(\n})/', "}", $buffer);
    $buffer = preg_replace('/(}\n})/', "}}", $buffer);
    
    return $buffer;
}

function findCompressJS($buffer) {
    /**********************************************/
    /**********************************************/
    /**********************************************/
    /* Сжатие JS */
    preg_match_all ('/<script[^>]*src="(.*?)"[^>]*>/', $buffer, $out_script);
    foreach ($out_script[0] as $key=>$val ) {
       $need = true;
      /* Исключим скрипты на сторонних сайтах  */
      if (preg_match ('/http.*/i' , $out_script[1][$key])) {
        if (!preg_match ('/http.*'.$_SERVER['HTTP_HOST'].'.*/i' , $out_script[1][$key])) {
          $need = false;
        }
        if ($need) {
          $out_script[1][$key] = str_replace ('https://'.$_SERVER['HTTP_HOST'], '' , $out_script[1][$key]);
          $out_script[1][$key] = str_replace ('http://'.$_SERVER['HTTP_HOST'], '' , $out_script[1][$key]);
        }
      }
      if (substr($out_script[1][$key], 0, 2) == '//') {
        $need = false;
      }
      
      /* Исключим всякие jquery */
      if (strpos($out_script[1][$key], 'jquery') > 0) {
        $need = false;
      }      
      /* Исключим уже сжатые*/
      if (strpos($out_script[1][$key], '.min.js') > 0) {
        $need = false;
      }      

      

      if ($need) {
        //compressLog($out_script[1][$key]);
        $file_js = $_SERVER['DOCUMENT_ROOT'].$out_script[1][$key];
        if (strpos($file_js, '?') > 0) {
          $file_js = substr($file_js, 0 ,strpos($file_js, '?'));
        }
        if (file_exists($file_js)) {
          $curinfo['size'] = filesize($file_js);
          $curinfo['time'] = filemtime($file_js);
          $file_js_new = str_replace('.js', '.compress.js', $file_js);
          $link_new = str_replace('.js', '.compress.js', $out_script[0][$key]);
          $info = array();
          if (file_exists($file_js_new)) {
            $js_old_compress = file_get_contents($file_js_new);
            $js_old_compress = substr($js_old_compress, strpos($js_old_compress,'-===') + 4);
            $ks_old_compress = substr($js_old_compress, 0, strpos($js_old_compress, '===-'));
            $info = unserialize($js_old_compress);
          }
          if (($curinfo['size'] != $info['size']) or ($curinfo['time'] != $info['time']))  {
            file_put_contents($file_js_new, '/* !!! Оригинальный файл / Original file :'.$out_script[1][$key].' !!! Этот файл будет сгенерирован автоматически при изменении оригинала / This file will be automaticly update  -==='.serialize($curinfo).'===- */'.compressJS(file_get_contents($file_js)));
          }
          $buffer = str_replace($out_script[0][$key], $link_new, $buffer);
        }
      }
    }
    return $buffer;
}


function compressHtml($buffer) {

    /**********************************************/
    /**********************************************/
    /**********************************************/
    /* Сжатие HTML */

    $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
    $buffer = str_replace(array("\r\n", "\r", "\n", "\t"), '', $buffer);
    $buffer = str_replace(array("  ", '   ', '    ', '     '), ' ', $buffer);
    $buffer = str_replace('<!DOCTYPE html>', "<!DOCTYPE html>\n",$buffer);

    return $buffer;
}

function compressHtmlCssJs($buffer) {
  global $debug;
	if(empty(Kernel::$in['handle']) && empty(Kernel::$in['file']) && empty(Kernel::$in['download']) && empty(Kernel::$in['vip3']) && flag!=1) {
    $buffer = compressHtml($buffer);
    $buffer = findCompressCss($buffer);
    $buffer = findCompressJS($buffer);
  }    
    return $buffer;
}
