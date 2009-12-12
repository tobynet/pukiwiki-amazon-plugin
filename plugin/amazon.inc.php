<?php
///////////////////////////////////////////////// 要変更箇所
// アソシエイト ID
define('AMAZON_AID','');
// アクセスキーID http://www.amazon.co.jp/gp/feature.html?docId=451209 から取得
define('AWS_ACCESS_KEY_ID','');
// サブスクリプションID（旧Developer Token? 買物かご機能を使うのに必要)
define('AWS_SUBSCRIPTION_ID','');
// 秘密キー(Product Advertising API 署名認証に必要)
define('SECRET_ACCESS_KEY','');

///////////////////////////////////////////////// 変更してもよい箇所
define('USE_CACHE', false); // キャッシュ機能の使用の有無
define('AMAZON_EXPIRE_CACHE', 24); // キャッシュの有効期限(単位:時間)
define('AMAZON_ALLOW_CONT', true); // true にすると、紹介本文取り込みが可能(商品紹介情報を取得できるリクエストが不明のため未実装）
define('USE_CARGO', true); // true にすると買物かごを使用可能
// 写影なしの画像/買物かごのアイコン
define('AMAZON_NO_IMAGE','./image/noimage.gif');
define('AMAZON_CARGO','./image/remote-buy-jp.gif');
// 画像サイズ SwatchImage, SmallImage, ThumbnailImage, TinyImage, MediumImage, LargeImage
define('PLUGIN_AMAZON_IMAGE_SIZE', 'MediumImage');
// 表示エンコード utf-8, sjis, euc-jp, jis, ascii
define('DISPLAY_ENCODING', 'euc-jp');
// デフォルトの表示形式
define('AMAZON_PLUGIN_DEFAULT_ITEM_TYPE', '');
// デフォルトの整列形式
define('AMAZON_PLUGIN_DEFAULT_ALIGN', 'right');

///////////////////////////////////////////////// 変更してはならない箇所
define('AMAZON_SHOP','http://www.amazon.co.jp/exec/obidos/ASIN/');
define('AMAZON_CART','http://www.amazon.co.jp/gp/aws/cart/add.html');
define('AMAZON_XML', 'http://webservices.amazon.co.jp/onca/xml?');

function plugin_amazon_init() {
  global $amazon_body;

  $amazon_body = <<<EOD
-作者: [[ここ編集のこと]]
-評者: お名前
-日付: &date;
**お薦め対象
[[ここ編集のこと]]

#amazon(,clear)
**感想
[[ここ編集のこと]]

// まず、このレビューを止める場合、全文を削除し、ページの[更新ボタン]を押してください！(PukiWiki にはもう登録されています)
// 続けるなら、上の、[[ここ編集のこと]]部分を括弧を含めて削除し、書き直してください。
// お名前、部分はご自分の名前に変更してください。私だと、閑舎、です。
// **お薦め対象、より上は、新しい行を追加しないでください。目次作成に使用するので。
// //で始まるコメント行は、最終的に全部カットしてください。目次が正常に作成できない可能性があります。
#comment
EOD;
}

function plugin_amazon_convert() {
  global $script, $vars;
  $aryargs = func_get_args();
  if (func_num_args() == 0) { // レビュー作成
    $s_page = htmlspecialchars($vars['page']);
    if ($s_page == '') {
      $s_page = $vars['refer'];
    }
    $ret = <<<EOD
<form action="$script" method="post">
 <div>
  <input type="hidden" name="plugin" value="amazon" />
  <input type="hidden" name="refer" value="$s_page" />
  ASIN:
  <input type="text" name="asin" size="30" value="" />
  <input type="submit" value="レビュー編集" /> (ISBN 10 桁 or ASIN 12 桁)
 </div>
</form>
EOD;
    return $ret;
  } elseif (func_num_args() > 4) {
    return false;
  }

  $align = strtolower(trim($aryargs[1]));
  if ($align == 'clear') return '<div style="clear:both"></div>'; // 改行挿入
  if ($align == '') $align = AMAZON_PLUGIN_DEFAULT_ALIGN;
  if (preg_match("/^(right|left|center)$/", $align) == false) return false;

  $item = htmlspecialchars(trim($aryargs[2])); // for XSS
  if ($item == '') {
    $item = AMAZON_PLUGIN_DEFAULT_ITEM_TYPE;
  }

  $check = new amazon_check_asin(htmlspecialchars($aryargs[0])); // for XSS
  if ($check->is_asin) 
  {
    if ($item == 'image') 
    {
      $info = new amazon_getinfo($check->asin);
      $div = "<div class='amazon_img' style='float:$align'>";
      $div .= amazon_get_imagelink($check->asin, $info) . '</div>';
    } 
    elseif (preg_match("/^((no)?contentc?|subscriptc?)$/", $item)) 
    {
      $iscargo = false;
      if (preg_match("/^((no)?contentc|subscriptc)$/", $item)) 
      {
        if (USE_CARGO) $iscargo = true;
        $item = substr($item, 0, strlen($item) - 1);
      }
      $info = new amazon_getinfo($check->asin);
      if (preg_match("/^((no)?content)$/", $item)) 
      {
        $div = "<div class='amazon_imgetc' style='float:$align'>";
        $div .= amazon_get_imagelink($check->asin, $info) . '</div>';
      }
      if ($iscargo) 
      {
        $div .= "<form method='POST' action='" . AMAZON_CART . "'>";
        $div .= "<div class='amazon_sub' style='text-align:$align'>";
        $div .= "<input type='hidden' name='ASIN.1' value='" . $check->asin . "'>";
        $div .= "<input type='hidden' name='Quantity.1' value='1'>";
        $div .= "<input type='hidden' name='AssociateTag' value='" . AWS_ACCESS_KEY_ID . "'>";
        $div .= "<input type='hidden' name='SubscriptionId' value='" . AWS_SUBSCRIPTION_ID . "'>";
      } 
      else $div .= "<div class='amazon_sub' style='text-align:$align'>";

      $div .= '<a href="' . AMAZON_SHOP . $check->asin.  '/'
          . AMAZON_AID . '">' . $info->items['title'] . '</a><br />';

      if($info->items['author'] != '') {
        $div .= $info->items['author'] . "<br />";
      }
      if($info->items['manufact'] != '') {
        $div .= $info->items['manufact'] . "<br />";
      }
      if($info->items['lprice'] != '')
      {
        $div .= "<b>" . mb_convert_encoding("参考価格", SOURCE_ENCODING, DISPLAY_ENCODING) . ":</b><s> " . $info->items['lprice'];
        $div .= "(" . mb_convert_encoding("税込", SOURCE_ENCODING, DISPLAY_ENCODING) .")</s><br />";
      }
      if($info->items['nprice'] != '')
      {
        $div .= "<b>" . mb_convert_encoding("価格", SOURCE_ENCODING, DISPLAY_ENCODING) . ":</b><b style='color: #990000;'> " . $info->items['nprice'];
        $div .= "(" . mb_convert_encoding("税込", SOURCE_ENCODING, DISPLAY_ENCODING) .")</b><br />";
      }
      if($info->items['asaved'] != '')
      {
        $div .= "<b>" . mb_convert_encoding("OFF", SOURCE_ENCODING, DISPLAY_ENCODING) . ":</b><span style='color: #990000;'> " . $info->items['asaved'];
        $div .= "(" . $info->items['psaved'] . mb_convert_encoding("％", SOURCE_ENCODING, DISPLAY_ENCODING) . ")</span></b></div>";
      }
      $div .= "<div class='amazon_avail' style='text-align:$align'>";
      if($info->items['avail'] != '')
      {
        $div .= "<b>" . mb_convert_encoding("在庫状態", SOURCE_ENCODING, DISPLAY_ENCODING) . ":</b> " . $info->items['avail'] . "</div>";
        if ($iscargo) $div .= "<input type='image' src='" . AMAZON_CARGO . "' name='submit' style='border-style:none;' value='" . mb_convert_encoding("買物かごへ", SOURCE_ENCODING, DISPLAY_ENCODING) ."'><br /></form>";
      }
      else
      {
        $div .= "<b>" . mb_convert_encoding("在庫状態", SOURCE_ENCODING, DISPLAY_ENCODING) . ":</b> " . mb_convert_encoding("現在在庫がありません", SOURCE_ENCODING, DISPLAY_ENCODING) . "</div>";
        if ($iscargo) $div .= "<br /></form>";
      }
      if ($item == 'content') $div .= "<br />" . $info->items['content'] . '<div style="clear:both"></div>';
    } 
    else 
    {
      if ($item == '') 
      {
        $info = new amazon_getinfo($check->asin);
        $item = $info->items['title'];
      }
      $div = "<div class='amazon_img' style='float:$align'>";
      if(PLUGIN_AMAZON_IMAGE_SIZE == 'SwatchImage')
      {
        $div .= "<table class='amazon_tbl' width='21px'><tr><td class='amazon_td'>";
      }
      else if(PLUGIN_AMAZON_IMAGE_SIZE == 'SmallImage')
      {
        $div .= "<table class='amazon_tbl' width='53px'><tr><td class='amazon_td'>";
      }
      else if(PLUGIN_AMAZON_IMAGE_SIZE == 'ThumbnailImage')
      {
        $div .= "<table class='amazon_tbl' width='53px'><tr><td class='amazon_td'>";
      }
      else if(PLUGIN_AMAZON_IMAGE_SIZE == 'TinyImage')
      {
        $div .= "<table class='amazon_tbl' width='78px'><tr><td class='amazon_td'>";
      }
      else if(PLUGIN_AMAZON_IMAGE_SIZE == 'MediumImage')
      {
        $div .= "<table class='amazon_tbl' width='114px'><tr><td class='amazon_td'>";
      }
      else if(PLUGIN_AMAZON_IMAGE_SIZE == 'LargeImage')
      {
        $div .= "<table class='amazon_tbl' width='355px'><tr><td class='amazon_td'>";
      }
      else
      {
        $div .= "<table class='amazon_tbl'><tr><td class='amazon_td'>";
      }
      $div .= amazon_get_imagelink($check->asin, $info) . '</td></tr>';
      $div .= '<tr><td class="amazon_td"><a href="' . AMAZON_SHOP . $check->asin;
      $div .= '/' . AMAZON_AID . '">' . $item . '</a></td></tr></table></div>';
    }
    return $div;
  }
  else 
  {
    if (htmlspecialchars(trim($aryargs[0])) == 'popup')
    {
      $amazon_aid = AMAZON_AID;
      $div .= <<<EOD
<script type="text/javascript" src="http://www.assoc-amazon.jp/s/link-enhancer?tag=$amazon_aid&o=9">
</script>
<noscript>
    <img src="http://www.assoc-amazon.jp/s/noscript?tag=$amazon_aid" alt="" />
</noscript>    
EOD;
      return $div;
    }
    else
    {
      return false;
    }
  }
}

function plugin_amazon_action() 
{
  global $vars, $script;
  global $amazon_body;

  $check = new amazon_check_asin(htmlspecialchars(rawurlencode(strip_bracket($vars['asin']))));
  if (! $check->is_asin) 
  {
    $retvars['msg'] = "ブックレビュー編集";
    $retvars['refer'] = $vars['refer'];
    $s_page = $vars['refer'];
    $r_page = $s_page . '/' . $check->asin;
    $retvars['body'] = plugin_amazon_convert();
    return $retvars;
  }

  $s_page = $vars['refer'];
  $r_page = $s_page . '/' . $check->asin;
  $r_page_url = rawurlencode($r_page);

  if (!check_readable($r_page, false, false)) 
  {
    header("Location: $script?cmd=read&page=$r_page_url");
  } 
  elseif (check_editable($r_page, false, false)) 
  {
    $info = new amazon_getinfo($check->asin);
    $title = $info->items['title'];
    if ($title == '' or preg_match('/^\//', $s_page)) 
    {
      header("Location: $script?cmd=read&page=" . encode($s_page));
    }
    $body = "#amazon($check->asin,,image)\n*$title\n" . $amazon_body;
    amazon_review_save($r_page, $body);
    header("Location: $script?cmd=edit&page=$r_page_url");
  } else return false;
  die();
}

function plugin_amazon_inline() 
{
  $aryargs = func_get_args();
  if (func_num_args() < 2 or func_num_args() > 3) return false;
  elseif (func_num_args() == 2) $item = 'title';
  else 
  {
    $item = htmlspecialchars(trim($aryargs[1])); // for XSS
    if (preg_match("/^(title|author|manufact|lprice|nprice|asaved|psaved|avail|content|image)$/", $item) == false)
     return false;
  }

  $asin = htmlspecialchars($aryargs[0]);

  $check = new amazon_check_asin($asin); // for XSS
  if ($check->is_asin) 
  {
    if ($item == 'image') 
    {
      $info = new amazon_getinfo($check->asin);
      return amazon_get_imagelink($check->asin, $info);
    } 
    elseif (preg_match("/^(title|author|manufact|content)$/", $item) == true) 
    {
     $info = new amazon_getinfo($check->asin);
      if ($item == 'title') 
      {
        return '<a href="' . AMAZON_SHOP . $check->asin . '/' . AMAZON_AID . '">' . $info->items[$item] . '</a>';
      } else return $info->items[$item];
    } 
    else 
    {
      $info = new amazon_getinfo($check->asin);
      return $info->items[$item];
    }
  }
}

// 書籍データを保存
function amazon_review_save($page, $data) 
{
  $filename = DATA_DIR . encode($page) . ".txt";

  if (!is_readable($filename))
    if (amazon_savefile($filename, $data)) return true;
  return false;
}

function amazon_get_imagelink($asin, $info) 
{
  if (! preg_match("/^[0-9A-Za-z]+$/", $asin)) return false;
  if ($info->items['image'] == '') $info->items['image'] = AMAZON_NO_IMAGE;
  $imagelink = '<a href="' . AMAZON_SHOP . $asin . '/' . AMAZON_AID . '">';
  $imagelink .= '<img src="' . $info->items['image'] . '" alt="' . $info->items['title'] . '" border="0" /></a>';
  return $imagelink;
}

class amazon_check_asin 
{
  var $asin;
  var $ext;
  function amazon_check_asin($asin_old) 
  {
    $tmpary = array();
    if (preg_match("/^([A-Z0-9]{10}).?([0-9][0-9])?$/", $asin_old, $tmpary) == true) {
      $this->asin = $tmpary[1];
      $this->ext = $tmpary[2];
      if ($asin_ext == '') $this->ext = "09";
      $this->is_asin = true;
    } else $this->is_asin = false;
  }
}

function amazon_savefile($file, $body) 
{
  $lock = "$file.lock.amazon"; // For Lock
  for ($loop = 10; $loop > 0; $loop--) 
  {
    if (! is_readable($lock)) break;
    usleep(300000);
  }
  $fl = fopen($lock, "wb");
  fwrite("w");
  fclose($fl);
  $fp = fopen($file, "wb");
  if (! $fp) {
    unlink($lock);
    return false;
  }
  fwrite($fp, $body);
  fclose ($fp);
  unlink($lock);
  return true;
}

function amazon_getfile($file) {
  if (! preg_match('/^http:/', $file)) { // For Lock
    $lock = "$file.lock.amazon";
    for ($loop = 10; $loop > 0; $loop--) {
      if (! is_readable($lock)) break;
      usleep(300000);
    }
    if ($loop == 0) return '';
  }
  $fp = fopen($file, "rb");
  if (! $fp) return '';
  $body = '';
  while (!feof($fp)) $body .= fread($fp, 4096);
  fclose ($fp);
  return $body;
}
  
function amazon_getpage($url)
{
    $data = http_request($url);
    return ($data['rc'] == 200) ? $data['data'] : '';
}

class amazon_getinfo 
{
  var $items = array();

  function amazon_getinfo ($asin) 
  {
    if(USE_CACHE)
    {
      $xmlfile = CACHE_DIR . "ASIN" . $asin . ".xml";
      $imgfile = CACHE_DIR . "ASIN" . $asin . ".jpg";

      if (!is_readable($xmlfile) || (AMAZON_EXPIRE_CACHE * 3600 < time() - filemtime($xmlfile))) 
      {
        $url = create_url($asin);
        $xml = amazon_getpage($url);
        $xml = mb_convert_encoding($xml, SOURCE_ENCODING, 'UTF-8');
        amazon_savefile($xmlfile, $xml);

        preg_match('/<' . PLUGIN_AMAZON_IMAGE_SIZE . '><URL>([^<]*)</', $xml, $tmpary);
        $tmpfile = trim($tmpary[1]);
        $img = amazon_getpage($tmpfile);
        $fp = fopen($tmpfile, 'wb');
        fwrite($fp, $img);
        fclose($fp);
        unlink($tmpfile);
        amazon_savefile($imgfile, $img);
      }

      $body = amazon_getfile($xmlfile);
      $tmpary = array();
      $this->items['image'] = $imgfile;
    }
    else
    {
      $url = create_url($asin);
      $body = amazon_getpage($url);
      $body = mb_convert_encoding($body, SOURCE_ENCODING, 'UTF-8');
      $tmpary = array();
      $this->items['image'] = (preg_match('/<' . PLUGIN_AMAZON_IMAGE_SIZE . '><URL>([^<]*)</', $body, $tmpary)) ? trim($tmpary[1]): "";
    }
    
    $this->items['title'] = (preg_match('/<Title>([^<]*)</', $body, $tmpary)) ? trim($tmpary[1]) : "";
    $this->items['manufact'] = (preg_match('/<Manufacturer>([^<]*)</', $body, $tmpary)) ? trim($tmpary[1]): "";
    $this->items['lprice'] = (preg_match('/<FormattedPrice>([^<]*)<\/FormattedPrice><\/ListPrice>/', $body, $tmpary))? trim($tmpary[1]): "";
    $this->items['nprice'] = (preg_match('/<FormattedPrice>([^<]*)<\/FormattedPrice><\/LowestNewPrice>/', $body, $tmpary))? trim($tmpary[1]): "";
    $this->items['asaved'] = (preg_match('/<FormattedPrice>([^<]*)<\/FormattedPrice><\/AmountSaved>/', $body, $tmpary))? trim($tmpary[1]): "";
    $this->items['psaved'] = (preg_match('/<PercentageSaved>([^<]*)</', $body, $tmpary))? trim($tmpary[1]): "";
    $this->items['avail'] = (preg_match('/<Availability>([^<]*)</', $body, $tmpary))? trim($tmpary[1]): "";

    if (AMAZON_ALLOW_CONT) 
    {
      $this->items['content'] = (preg_match('/<Content>([^<]*)</', $body, $tmpary))? trim($tmpary[1]): "";
      $this->items['content'] = preg_replace("'&amp;'", '&', $this->items['content']);
      $this->items['content'] = preg_replace("'&lt;'", '<', $this->items['content']);
    }
    else 
    {
      $this->items['content'] = '';
    }

    $count = preg_match_all('|<Author>(.[^<]*)<|U', $body, $tmpary);
  
    if ($count > 0) 
    {
      for ($i=0; $i<$count; $i++) 
      {
        if ($i > 0) $this->items['author'] .= ", ";
        $this->items['author'] .= trim($tmpary[1][$i]);
      }
    } 
    else 
    {
      $count = preg_match_all('|<Director>(.[^<]*)<|U', $body, $tmpary);
      if ($count > 0) 
      {
        for ($i=0; $i<$count; $i++) 
        {
          if ($i > 0) $this->items['author'] .= ", ";
          $this->items['author'] .= trim($tmpary[1][$i]);
        }
      } 
      else 
      {
        $count = preg_match_all('|<Artist>(.[^<]*)<|U', $body, $tmpary);
        if ($count > 0)
        {
          for ($i=0; $i<$count; $i++) 
          {
            if ($i > 0) $this->items['author'] .= ", ";
            $this->items['author'] .= trim($tmpary[1][$i]);
          }
        }
        else
        {
          $count = preg_match_all('|<Actor>(.[^<]*)<|U', $body, $tmpary);
          if ($count > 0) 
          {
            for ($i=0; $i<$count; $i++) 
            {
              if ($i > 0) $this->items['author'] .= ", ";
              $this->items['author'] .= trim($tmpary[1][$i]);
            }
          } 
        }
      }
    }
  }
}

function create_url($asin)
{
  $header = "GET\nwebservices.amazon.co.jp\n/onca/xml\n";
  $param  = "AWSAccessKeyId=" . AWS_ACCESS_KEY_ID;
  $param .= "&ItemId=" . $asin;
  $param .= "&Operation=ItemLookup";
  $param .= "&ResponseGroup=" . rawurlencode("ItemAttributes,Images,Offers");
  $param .= "&Service=AWSECommerceService";
  $param .= "&Timestamp=" . rawurlencode(gmdate('Y-m-d\TH:i:s\Z'));
  $param .= "&Version=2009-03-31";
  $sign   = rawurlencode(base64_encode(hash_hmac('sha256',$header.$param,SECRET_ACCESS_KEY,true)));
  $url    = AMAZON_XML . $param . "&Signature=" . $sign;
  return $url;
}
?>
