<?php
/**
 * Created by PhpStorm.
 * User: vityakut
 * Date: 05.10.18
 * Time: 19:23
 */
include "simple_html_dom.php";
error_reporting (E_ERROR);
ini_set('mysqli.reconnect', 1);
$mem_start = memory_get_usage();
//define('DB_HOSTNAME', 'localhost');
//define('DB_USERNAME', 'optomv');
//define('DB_PASSWORD', '0VYfIvw5eZgU');
//define('DB_DATABASE', 'optomv');
include "config.php";

$imgpath = "catalog/products/";
$tmpFile = "lastupdate.log";

mb_internal_encoding('UTF-8');
$baseurl = "https://get360.ru/";

global $catlist;
global $proxy;
global $counCurl;
$counCurl = 0;
$proxy = "http://37.187.99.146:3128";
//$proxy = false;

if (in_array("cli", $argv)) {
    print "start\r\n";
    $updated = 0;
    $inserted = 0;
    $insertCat = 0;
    $deleted = 0;
    $startTime = time();
    print DB_USERNAME . "\r\n";
//    changeProxy();
    $db = connect_db();
    $catlist = getCatTree();
    insertCategories($db, $catlist);
    getProducts($db, $catlist);

//var_dump($catlist);


    $db->close();
    $donemsg = "Добавлено товаров: " . strval($inserted) . "\n";
    $donemsg .= "Добавлено категорий: " . strval($insertCat) . "\n";
//    $donemsg .= "Обновлено товаров: ". strval($updated)."\n";
//    $donemsg .= "Удалено товаров: ". strval($deleted)."\n";
    $donemsg .= "Время выполненения: " . strval(time() - $startTime) . " сек\n";
    $donemsg .= sprintf("\n\nMemory usage: %s Mb\n", strval(round((memory_get_usage() - $mem_start) / 1048576, 2)));
    print($donemsg);
    tgNotificate($donemsg);
    $dtime = date("Y-m-d H:i:s");
//    file_put_contents($tmpFile, $dtime);


}
elseif (in_array("proxy", $argv)){
    checkProxyList();
} else {
    print "fucking fuck!\r\n";
    exit();

}

function getProducts($db, $catlist){
    $doneArr = array(536, 537, 538, 606, 607, 539, 608, 609, 540, 541, 542, 543, 544, 611, 612, 613, 614,615,616,617,618,619,620,621,622,623,624,625,626,
        627,628,629,630,631,632,633, 634,635,636, 637, 545,546,547,548,549,550,551,552,553,554,
        555, 556,557,558,559,638,639,640,641,642,643,644,645,646,647,648,649,650,651,560,561,
        652,653,562,563,564,654,655,656,657,658,565,566,567,659,660,661,662,663,664,665,666,667,668,669,670,671,672,673,568,569,570,571,572,573,574,575,576,577,
        578,579,580,581,583,584,674,675,676,677,678,679,680,541, 610);
    foreach ($catlist as $cat) {

//    $cat = array_values($catlist)[4];
        $page = 1;

        if (!(($cat['parent'])) && !(in_array($cat['iddb'], $doneArr))){
//        if (!(($cat['parent'])) && ($cat['iddb'] == 610)){
            if (file_exists("img2aria-".strval($cat['iddb']).".txt")) {
                unlink("img2aria-".strval($cat['iddb']).".txt");
            }
            $countpage = 1;
            $catproducts = array();
            while ($page <= $countpage){
//            while ($page <= 19){
                $link = sprintf("%s%s?view_as=gallery&product_items_per_page=48", $cat['href'], ($page == 1) ? "" : "/page_".strval($page));
//                print $link."\n";
                $catpage = get_page($link);
                if ($page == 17){
                    $tmp = $catpage->find(".b-catalog-panel__pagination a.b-pager__link");
                    $countpage = $tmp[sizeof($tmp)-2]->innertext;
                }
                foreach ($catpage->find(".b-product-gallery .b-product-gallery__item") as $prod) {
                    $catproducts[] = array(
                        "name" => trimspace($prod->find(".b-product-gallery__title")[0]->innertext),
                        "href" => $prod->find(".b-product-gallery__title")[0]->attr["href"],
                        "id" => $prod->attr["data-product-id"],
                        "catid" => $cat['iddb']
                    );
                }
                $page++;
            }
            print "count products in " .$cat['name']. " - " . strval(sizeof($catproducts)) . "\n";
            if (sizeof($catproducts) == 0){
                tgNotificate("count products in " .$cat['name']. " - " . strval(sizeof($catproducts)) . "\n");
            }
            foreach ($catproducts as $catproduct) {
                getProd($catproduct);
                check_db_connect($db);
                insertProduct($db, $catproduct);
                insertAttributes($db, $catproduct);
            }
//            changeProxy();
            $sl = rand(20, 40);
            tgNotificate("Change category. last - ". $cat['iddb']);
            printf("sleep %s\n", $sl);
            sleep($sl);
        }
    }
}

function insertAttributes($db, $product){
    $sqlSelectAttr = "SELECT `attribute_id` FROM oc_attribute where `attribute_group_id` = '6' and `attribute_id` in (SELECT `attribute_id` FROM oc_attribute_description where `name` = '%s');\r\n";
    $sqlInsAttribute = "INSERT IGNORE INTO oc_attribute (attribute_group_id, sort_order) VALUE ('6', '0');\r\n";
    $sqlInsAttrDescript = "INSERT IGNORE INTO oc_attribute_description (attribute_id, language_id, name) VALUE ('%s', '1', '%s');\r\n";
    $sqlInsAttrValue = "INSERT IGNORE INTO oc_product_attribute (product_id, attribute_id, language_id, text) VALUE ('%s', '%s', '1', '%s');\r\n";
    foreach ($product['attr'] as $attr) {
        $sql =  sprintf($sqlSelectAttr, $db->real_escape_string($attr['attr']));
        if ($res = $db->query($sql)){
            if ($res->num_rows > 0){
                $attrid = $res->fetch_row()[0];
            } else{
                if ($db->query($sqlInsAttribute)){
                    $attrid = $db->insert_id;
                    $db->query(sprintf($sqlInsAttrDescript, $attrid, $db->real_escape_string($attr['attr'])));
                } else {
                    exit('Ошибка базы данных ' . $db->error);
                }
            }
        } else {
            exit('Ошибка базы данных ' . $db->error);
        }
        if (!($db->query(sprintf($sqlInsAttrValue, $product['iddb'], $attrid, $db->real_escape_string($attr['value']))))){
            exit('Ошибка базы данных ' . $db->error);
        }
    }
}

function insertProduct($db, &$product){
    global $inserted;
    $date = date("Y-m-d");
    $dtime = date("Y-m-d H:i:s");
    $sql = sprintf("INSERT IGNORE INTO oc_product (model, sku, location, quantity, stock_status_id, image, manufacturer_id, shipping, price, tax_class_id, date_available, subtract, status, date_added, date_modified, upc, ean, jan, isbn, mpn) VALUES ('%s', '%s', '', '100', '7', '%s', '0', '1', '%s', '0', '%s', '0', '1','%s', '%s', '', '', '', '', '');\r\n", strval($product['id']), $product['sku'], $product['images'][0], strval($product['price']), strval($date), strval($dtime), strval($dtime));

    if ($db->query($sql)){
        $lastid = $db->insert_id;
        $product['iddb'] = $lastid;
        $inserted++;
        $name = $db->real_escape_string($product['name']);
        $description = $db->real_escape_string($product['description']);
        $sql = sprintf("INSERT IGNORE INTO oc_product_description (product_id, language_id, name, description, tag, meta_title, meta_description, meta_keyword) values ('%s', '1', '%s', '%s', '', 'Купить %s в интернет магазине OPTOM-V43', '%s', '');\r\n", strval($lastid), $name, $description , $name, $db->real_escape_string(mb_substr(strip_tags($product['description'])), 0, 200));
        $sql .= sprintf("INSERT IGNORE INTO oc_product_to_category (product_id, category_id) values ('%s', '%s');\r\n", strval($lastid), strval($product['catid']));
        $sql .= sprintf("INSERT IGNORE INTO oc_product_to_store (product_id, store_id) values ('%s', '0');\r\n", strval($lastid));
        $sql .= sprintf("INSERT IGNORE INTO oc_product_to_layout (product_id, store_id, layout_id) values ('%s', '0', '0');\r\n", strval($lastid));
        $sql .= sprintf("INSERT IGNORE INTO oc_url_alias (query, keyword) values ('product_id=%s', '%s');\r\n", strval($lastid), strval($lastid)."-".alias($product['name']));
        $sql .= "INSERT INTO oc_product_image (product_id, image, sort_order) VALUES ";
        foreach ($product['images'] as $img){
            $sql .= sprintf("('%s', '%s', '0'), ", $lastid, $img);
        }
        $sql = substr($sql, 0, strlen($sql)-2);
        $sql .= ";\r\n";
        if ($db->multi_query($sql)){
            do {
                if ($res = $db->store_result()) {
                    $res->free();
                }
            } while ($db->more_results() && $db->next_result());
            unset($sql);
        } else {
            exit('Ошибка базы данных ' . $db->error);
        }

    } else {
        exit('Ошибка базы данных ' . $db->error);
    }


}

function getProd(&$product){
    print $product['name']."\n----\n";
    $html = get_page($product['href']);
    $price = preg_replace("/([^ \d\.\,])/u", "", $html->find(".b-product-cost__price span")[0]->innertext);
    $product['price'] = addPrice($price);
    $product['sku'] = trim($html->find(".b-product-data .b-product-data__item_type_sku span")[0]->plaintext);
    $product['description'] = preg_replace('/((get|гет)\s*360)/iu', "OPTOM-V43", trimspace($html->find(".b-page__row .b-user-content")[0]->innertext, false));
    $product['images'] = array();
    $product['attr'] = array();
    foreach ($html->find(".b-product__container .b-extra-photos .b-extra-photos__item, .b-product__container .b-product-view__image-link") as $img) {
        $product['images'][] = downloadImage($img->attr['href'], $product['catid']);
    }
    foreach ($html->find(".b-product-info tr") as $row){
        $tds = $row->find("td.b-product-info__cell");
        if ($tds){
            $product['attr'][] = array(
                "attr"  => trimspace($tds[0]->plaintext),
                "value" => trimspace($tds[1]->plaintext)
            );
        }
    }
}

function insertCategories($db, &$catl){
    $catDb = getCatlistFromDb($db);
    print "start update categories\n";
    foreach ($catl as &$cat) {
        if ($cat['lvl'] == 0 && $cat['cid'] != 0){
            if (in_array($cat['name'], $catDb)){
                $cat['iddb'] = array_search($cat['name'], $catDb);
            } else{
                $cat['iddb'] = insertCategory($db, $cat['cid']);
            }
            unset($cat);
        }
    }
    foreach ($catl as &$cat) {
        if ($cat['lvl'] == 1 && $cat['cid'] != 0){
            if (in_array($cat['name'], $catDb)){
                $cat['iddb'] = array_search($cat['name'], $catDb);
            } else{
                $cat['iddb'] = insertCategory($db, $cat['cid']);
            }
            unset($cat);
        }
    }



}

function insertCategory($db, $cid){
    global $catlist;
    global $insertCat;
    $dtime = date("Y-m-d H:i:s");

    if (isset($catlist[$catlist[$cid]['pid']]['iddb'])){
        $sql = sprintf("INSERT INTO `oc_category` (`parent_id`, `top`, `column`, `status`, `date_added`, `date_modified`, `yomenu_content`, `sort_order`) values ('%s', '0', '1', '1', '%s', '%s', '', '%s');", strval($catlist[$catlist[$cid]['pid']]['iddb']), $dtime, $dtime, strval($catlist[$cid]['sort']));
        if ($db->query($sql)){
            $lastid = $db->insert_id;
            $insertCat++;
        } else {
            exit('Ошибка базы данных ' . $db->error);
        }
        $description = "";
        $sql = "INSERT INTO oc_category_to_store (category_id, store_id) values ('".$lastid."', '0');\r\n";
        $sql .= "INSERT INTO oc_category_to_layout (category_id, store_id, layout_id) values ('".$lastid."', '0', '0');\r\n";
        $sql .= sprintf("INSERT INTO oc_category_description (category_id, language_id, name, description, meta_title, meta_description, meta_keyword) values ('%s', '1', '%s', '%s', '%s', '%s' , '%s');\r\n", $lastid, $db->real_escape_string($catlist[$cid]['name']), $db->real_escape_string($description), $db->real_escape_string($catlist[$cid]['name']), $db->real_escape_string(strip_tags($description)), "");
        $sql .= sprintf("INSERT INTO oc_url_alias (query, keyword) values ('category_id=%s', '%s');\r\n", strval($lastid), strval($lastid)."-".alias($catlist[$cid]['name']));
        $sql .= "INSERT INTO oc_category_path (`category_id`, `path_id`, `level`) values";
        if ($catlist[$cid]['lvl'] == 0){
            $sql .= sprintf("('%s', '%s', '0');\r\n", strval($lastid), strval($lastid));
        } elseif ($catlist[$cid]['lvl'] == 1){
            $sql .= sprintf("('%s', '%s', '0'), ('%s', '%s', '1');\r\n", strval($lastid), strval($catlist[$catlist[$cid]['pid']]['iddb']), strval($lastid), strval($lastid));
        }

        if ($db->multi_query($sql)){
            do {
                if ($res = $db->store_result()) {
                    $res->free();
                }
            } while ($db->more_results() && $db->next_result());
            unset($sql);
        } else {
            exit('Ошибка базы данных ' . $db->error);
        }

    }
    if (isset($lastid)){
        printf("insert category %s - %s\n", $cid, $catlist[$cid]['name']);
        return $lastid;
    } else {
        exit("lastid not isset\n");
    }

}

function getCatTree(){
    global $baseurl;
    $badcategory = array("Брелоки", "Кнопочные телефоны", "Зажигалки", "Сувениры и подарки");
    $cat = array();
    $html = get_page($baseurl);
    $tmp = $html->find(".b-nav .b-nav__item");
    foreach ($tmp as $sort=>$item) {
        $tmpa = $item->find(".b-nav__link")[0];
        if ($tmpa->href != "/" && $tmpa->href != $baseurl){
            preg_match("/(?<=\/g)\d+(?=-)/", $tmpa->href, $cid);
            $link = preg_replace("/^\//", $baseurl, $tmpa->href);
            $link = preg_replace("/\?.*$/", "", $link);
            if (sizeof($cid) > 0 && !in_array(trim($tmpa->innertext), $badcategory)){
                $cid = $cid[0];
                $cat[intval($cid)] = array(
                    "name" => trimspace($tmpa->plaintext),
                    "href" => $link,
                    "cid" =>  intval($cid),
                    "pid" => 0,
                    "lvl" => 0,
                    "sort" => intval($sort),
                    "description" => "",//getCatDescription($link, $cid)
                    "parent" => false
                );
//                var_dump($cat[intval($cid)]['description']);
            }
            foreach ($item->find(".b-sub-nav__item .b-sub-nav__link") as $ssort => $subitem) {
                preg_match("/(?<=\/g)\d+(?=-)/", $subitem->href, $subid);
                if (sizeof($subid) > 0 && !in_array(trim($subitem->innertext), $badcategory)){
                    $cat[intval($cid)]["parent"] = true;
                    $subid = $subid[0];
                    $link = preg_replace("/^\//", $baseurl, $subitem->href);
                    $link = preg_replace("/\?.*$/", "", $link);
                    if ($cid != $subid){
                        $cat[intval($subid)] = array(
                            "name" => trimspace($subitem->plaintext),
                            "href" => $link,
                            "cid" =>  intval($subid),
                            "pid" => intval($cid),
                            "lvl" => 1,
                            "sort" => $ssort,
                            "parent" => false,
                            "description" => ""//getCatDescription($link, $subid)
                        );
                    }
                }
            }
        }

    }
    $cat[0] = array(
        "name" => "",
        "href" => "",
        "cid" =>  0,
        "pid" => 0,
        "iddb" => 0,
        "lvl" => 0
    );
    return $cat;
}

function getCatDescription($link, $cid = null){
    $html = get_page($link);
    $desk = $html->find(".b-page__row .b-user-content")[0];
    $deskstr = "";
    foreach($desk->find('img, .ck-alert') as $item) {
        if ($item){
            $item->outertext = '';
            $html->save();
        }

    }
    foreach ($desk->find("p") as $p) {
        $p->attr = null;
        foreach ($p->find("em, strong, i, b, button, input, span") as $i) {
            $i->outertext = "";
            $html->save();
        }
        if (strlen($p->innertext) > 10){
            $deskstr .= $p->outertext;
        }
    }
    $deskstr = preg_replace('/((get|гет)\s*360)/iu', "OPTOM-V43", $deskstr);
    printf("Category description received %s\n", $cid ? "for ".strval($cid): "");
    return $deskstr;
}

function get_page($url, $recursive = false){
    global $proxy;
    global $counCurl;
    $useragents = array(
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246",
        "Mozilla/5.0 (X11; CrOS x86_64 8172.45.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.64 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
        "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1",
        "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13"
    );
    $ch = curl_init ();
    if ($counCurl %  1000 == 0 && $counCurl > 0){
        $sl = rand(800, 1000);
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
    } elseif ($counCurl %  800 == 0 && $counCurl > 0){
        $sl = rand(500, 800);
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
    } elseif ($counCurl %  500 == 0 && $counCurl > 0){
        $sl = rand(200, 250);
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
    } elseif ($counCurl %  100 == 0 && $counCurl > 0){
        $sl = rand(40, 80);
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
    } elseif ($counCurl %  15 == 0 && $counCurl > 0){
        $sl = rand(3, 10);
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
    } elseif ($counCurl %  9 == 0 && $counCurl > 0){
        $sl = rand(3, 7);
        if (file_exists("cookie.txt")) {
            unlink("cookie.txt");
            print "куки почищены\n";
        }
//        changeProxy();
        printf("Sleep %s (total %s query)\n", $sl, $counCurl);
        sleep($sl);
    }
    else {
        usleep(rand(200, 2000));
        curl_setopt($ch , CURLOPT_COOKIESESSION, 0);
    }
    if ($recursive){
        curl_setopt($ch , CURLOPT_COOKIESESSION, 1);
    }
    curl_setopt($ch , CURLOPT_COOKIESESSION, 1);
    curl_setopt ($ch , CURLOPT_URL , $url);
    curl_setopt ($ch , CURLOPT_USERAGENT , $useragents[rand(0, sizeof($useragents)-1)]);
    curl_setopt ($ch , CURLOPT_RETURNTRANSFER , 1);

    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    curl_setopt($ch , CURLOPT_COOKIEFILE, 'cookie.txt');
    if ($proxy){
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt ($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    $content = curl_exec($ch);
    $err = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    print strval($counCurl+1) . " - ". $url;
    if (!($err)) {
         print " - http code " . $code ."\n";
    } else{
        print " - error code ". $err . "\n";
    }

    file_put_contents("lastdump.html", $content);
    curl_close($ch);

    if ($content && $code == 200){
        $html = str_get_html($content);
        $counCurl++;
        if (is_captcha($html)){
            print "CAPTCHA!!\nsleep 7\n";
            changeProxy();
            sleep(7);
            $html = get_page($url, true);
            file_put_contents("recdump.html", $html);
        }
        return $html;
    } elseif ($code == 429) {
        print "sleep 222\n";
        sleep(222);
        changeProxy();
        $html = get_page($url, true);
        file_put_contents("recdump.html", $html);
    } else {
        changeProxy();
        print "sleep 10\n";
        sleep(10);
        $html = get_page($url, true);
        file_put_contents("recdump.html", $html);
    }
    return $html;
}

function is_captcha($html){
    $elems = $html->find("a");
    $cap = false;
    foreach ($elems as $elem) {
        preg_match("/tiu\.ru/captcha/u", $elem->attr['href'], $tmp);
        if ($tmp && sizeof($tmp > 0)){
            print "CAPTCHA!!!!\n";
            print $elem->attr['href']."\n";
            $cap = true;
            print read_stdin();
            break;
        } else{
            $cap = false;
        }
    }
    return $cap;
}

function getCatlistFromDb($db){
    $getCatQuery = "SELECT category_id, name FROM oc_category_description";
    $catDb = array();
    if ($res = $db->query($getCatQuery)){
        while ($row = $res->fetch_row()){
            $catDb[$row[0]] = $row[1];
        };
    }
    return $catDb;
}

function delProductsFromDb($db, $prodDb){
    global $price;
    global $deleted;
    $eans = array();
    foreach ($price as $pr){
        $eans[] = $pr['ean'];
    }
    $proddelid = array();
    foreach ($prodDb as $prod) {
        if (!(in_array($prod['ean'], $eans))){
            $proddelid[] = $prod['prodId'];
        }
    }
    $deleted = sizeof($proddelid);
    if ($deleted > 0){
        $proddelstr = implode(", ", $proddelid);

        $queryDelete = "DELETE FROM oc_product WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_attribute WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_description WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_discount WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_filter WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_image WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_option WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_option_value WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_recurring WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_related WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_reward WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_special WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_to_category WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_to_layout WHERE product_id in (".$proddelstr.");\r\n";
        $queryDelete .= "DELETE FROM oc_product_to_store WHERE product_id in (".$proddelstr.");\r\n";

        if ($db->multi_query($queryDelete)){
            do {
                if ($res = $db->store_result()) {
                    $res->free();
                }
            } while ($db->more_results() && $db->next_result());
            unset($proddelstr);
            unset($proddelid);
        } else {
            exit('Ошибка базы данных ' . $db->error);
        }
    }
    printf("deleted %s products\n", $deleted);
}

function connect_db(){
    $link =  mysqli_connect(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD, DB_DATABASE);// or die (mysqli_error($link));
    if (!$link) {
        echo "Ошибка: Невозможно установить соединение с MySQL." . PHP_EOL;
        echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
        echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
        exit;
    }
    echo "connect db\n";
    mysqli_select_db($link, DB_DATABASE) or die(mysqli_error($link));
    mysqli_query($link, "set names utf8");
    return $link;
}

function openCsv($filename){
    $handle = fopen($filename, 'r');
    $price = array();
    while ($data = fgetcsv($handle,0,"#")) {
        if (mb_strtolower(trim($data[0])) != "name" && mb_strtolower(trim($data[2])) != "upc"){
            $pricerow = array();
            $pricerow['name'] = trim($data[0]);
            $pricerow['category'] = trim($data[1]);
            $pricerow['ean'] = trim($data[2]);
            $pricerow['model'] = trim($data[4]);
            $pricerow['size'] = $data[6];
            $pricerow['weight'] = trim($data[7]);
            $pricerow['insert'] = trim($data[8]);
            $pricerow['material'] = trim($data[9]);
            $pricerow['probe'] = trim($data[10]);
            $pricerow['description'] = trim($data[11]);

            $tmpprice = str_replace(",", ".", $data[5]);
            $tmpprice = preg_replace("/[^\d.]/", "", $tmpprice);
            $pricerow['price'] = $tmpprice;


            $price[$pricerow['ean']] = $pricerow;
        }
    }
    fclose($handle);
    printf("Получено %s товаров\r\n", sizeof($price));
    return $price;

}

function tgNotificate($msg){
    $token = "366207984:AAHBd_JORaXpJnPhSLqRVRUvOyzQsccCToQ";
    $chatID = "4447923";
    $messaggio = "<strong>OPTOM-V43</strong>\r\n";
    $messaggio .= $msg;
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $chatID;
    $url = $url . "&text=" . urlencode($messaggio)."&parse_mode=html";
    $ch = curl_init();
    $optArray = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROXY => 'http://proxy:proxy@proxy.vityakut.ru:1525'
    );
    curl_setopt_array($ch, $optArray);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function alias($str) {
    $str = mb_strtolower($str);
    $rus = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я',' ', '&nbsp;','&laquo;', '&raquo;', '"', '(', ')', ',', '.', '/', '\\', '+', '%', '&','$');
    $lat = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', '-','-','','', '', '', '', '', '', '-', '-', '', '', '', '');
    return str_replace($rus, $lat, $str);
}

function downloadImage($url, $cid){
    global $imgpath;
    $path = 'image/'.$imgpath.strval($cid)."/";
    preg_match("/\.\w{1,6}$/", $url, $ftype);
    $ftype = $ftype[0];
    $fname = md5($url).$ftype;
    $outfile = $imgpath.strval($cid)."/".$fname;


    image2aria($url, strval($cid)."/".$fname, "img2aria-".strval($cid).".txt");

//    if (!(is_dir(realpath($path)))){
//        mkdir($path, 0755, true);
//    }
//    if (!(file_exists(realpath($path)."/".$fname))){
//        curl_download($url, realpath($path)."/".$fname);
//    }

    return $outfile;
}

function image2aria($url, $out, $linkfile){
    $tmp = sprintf("%s\r\n out=%s\r\n", $url, $out);
    file_put_contents($linkfile, $tmp, FILE_APPEND);
}

function curl_download($url, $file)
{
    usleep(rand(100, 1000));
    global $proxy;
    $useragents = array(
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246",
        "Mozilla/5.0 (X11; CrOS x86_64 8172.45.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.64 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9",
        "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.111 Safari/537.36",
        "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1",
        "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13"
    );

    printf("start download ". $url."\n");
    $dest_file = @fopen($file, "a");
    $resource = curl_init();
    curl_setopt($resource,CURLOPT_USERAGENT,$useragents[rand(0, sizeof($useragents)-1)]);
    if ($proxy){
        curl_setopt($resource, CURLOPT_PROXY, $proxy);
    }
    curl_setopt($resource, CURLOPT_URL, $url);
    curl_setopt($resource, CURLOPT_FILE, $dest_file);
    curl_setopt($resource, CURLOPT_HEADER, 0);
    curl_exec($resource);
    curl_close($resource);
    fclose($dest_file);
    return true;
}

function trimspace($string, $trim=true){
    $string = str_replace("&nbsp;", " ", $string);
    $string = preg_replace("/\s{2,}/iu", " ", $string);
    if ($trim){
        $string = preg_replace("/(^\W+|[^a-zа-я\(\)]+$)/iu", "", $string);
    } else{
        $string = trim($string);
    }

    return $string;
}

function addPrice($price){
    $price = intval($price);
    if ($price < 100){
        $price = $price * 2;
    } elseif ($price < 500){
        $price = $price * 1.85;
    } elseif ($price < 1000){
        $price = $price * 1.6;
    }elseif ($price < 1500){
        $price = $price * 1.4;
    }else{
        $price = $price * 1.3;
    }
    return $price;
}

function hasChildCat($cid){
    global $catlist;
    $child = false;
    foreach ($catlist as $cat) {
        if ($cat['pid'] == $cid){
            $child = true;
        } else{
            $child = false;
        }
    }
    return $child;
}

function changeProxy(){
    global $proxy;
    if (file_exists("cookie.txt")) {
        unlink("cookie.txt");
        print "куки почищены\n";
    }
    $proxylist = array();

    if ($fh = fopen('proxy.txt', 'r')) {
        while (!feof($fh)) {
            $proxylist[] = trim(fgets($fh));
        }
        fclose($fh);
    }
    do {

        $rn = rand(0, sizeof($proxylist)-1);
        if (!(checkProxy($proxylist[$rn]))){
            unset($proxylist[$rn]);
        } else {
            if ($proxylist[$rn] != $proxy){
                $proxy = $proxylist[$rn];
                break;
            }
        }
    } while (true);
    print "proxy change to ".$proxy."\n";
    file_put_contents("proxy.txt", implode("\r\n", $proxylist));

}

function checkProxy($proxy=null)
{
    $url = "http://optom-v43.ru/";
    $ch = curl_init();
    $optArray = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PROXY => $proxy,
        CURLOPT_CONNECTTIMEOUT => 5
    );
    curl_setopt_array($ch, $optArray);
    $result = curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);
    if ($result){
        print $proxy. " ok\n";
        return true;
    } else{
        print $proxy. " fail - error ".$err."\n";
        return false;
    }

}

function read_stdin()
{
    $fr=fopen("php://stdin","r");   // open our file pointer to read from stdin
    $input = fgets($fr,128);        // read a maximum of 128 characters
    $input = rtrim($input);         // trim any trailing spaces.
    fclose ($fr);                   // close the file handle
    return $input;                  // return the text entered
}

function checkProxyList(){
    if ($fh = fopen('proxy.txt', 'r')) {
        while (!feof($fh)) {
            $tprox = trim(fgets($fh));
            if ((checkProxy($tprox))){
                $proxylist[] = $tprox;
            }
        }
        fclose($fh);

        file_put_contents("proxy.txt", implode("\r\n", $proxylist));
    }
}

function check_db_connect($db){
    if (!($db->ping())) {
        tgNotificate("mysql die");
        printf ("mysql reconnect sleep 5\n", $db->error);
        sleep(5);
        check_db_connect($db);
    }
}