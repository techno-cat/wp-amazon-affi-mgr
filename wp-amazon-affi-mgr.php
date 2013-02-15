<?php
/*
Plugin Name: Amazon Affiliate Manager
Plugin URI: http://www.nekonotechno.com/nekopress/
Description: Amazonアフィリエイトの色の管理
Version: 0.1
Author: neko
Author URI: http://www.nekonotechno.com/nekopress/
License: GPL2
*/

/*  Copyright 2013 neko (Twitter : @techno_neko)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function amazon_affi_mgr_add_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Amazonアフィリエイトの管理',
        'アフィリエイトの管理',
        8,
        __FILE__,
        'amazon_affi_mgr_admin_page'
    );
}
add_action( 'admin_menu', 'amazon_affi_mgr_add_admin_menu' );

function amazon_affi_mgr_add_css() {
    $my_css = WP_PLUGIN_URL . '/' . str_replace( '.php', '.css', plugin_basename(__FILE__) );
    wp_enqueue_style( 'amazon_affi_mgr_css', $my_css );
}
add_action( 'init', 'amazon_affi_mgr_add_css' );

function amazon_affi_mgr_admin_page() {
    AmazonAffiMgr::render();
}

class AmazonAffiMgr {
    const AMAZON_URL = 'http://rcm-jp.amazon.co.jp/e/cm';

    public $posts = array();
    public $link = array();

    function __construct() {
        global $wpdb;
        $sql  = "SELECT * FROM $wpdb->posts";
        $sql .= " WHERE post_status = 'publish'";
        $sql .= " AND post_content LIKE '%" . AmazonAffiMgr::AMAZON_URL . "%'";
        $this->posts = $wpdb->get_results( $sql, ARRAY_A );

        $link_this_page = str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] );
        $link_this_page = str_replace( '&affi_list=1', '', $link_this_page );
        $link_affi_list = $link_this_page . '&affi_list=1';

        $this->header = '
<div class="wrap">
  <h2>Amazonアフィリエイトの管理</h2>
  <p>アフィリエイトを含む記事の数: ' . count($this->posts) . '</p>
  <p>
    <a href="' . $link_this_page . '">操作画面</a> / <a href="' . $link_affi_list . '">一覧を表示</a>
  </p>';

        $this->footer = '</div>';
    }

    static public function render() {
        $mgr = new AmazonAffiMgr();
        echo $mgr->header;
        if ( !$mgr->posts ) {
            show_post_not_exists();
        }
        else if ( $_GET['affi_list'] ) {
            show_affi_list( $mgr->posts );
        }
        else {
            if ( $_POST['posted'] === 'Y' ) {
                // todo: replace
                show_mgr_page( $mgr->posts, true );
            }
            else {
                show_mgr_page( $mgr->posts, false );
            }
        }
        echo $mgr->footer;
    }
}

function show_post_not_exists() {
?>
  <p>アフィリエイトを含む記事はみつかりませんでした。</p>
<?php
}    
    
function show_affi_list(&$posts) {
?>
  <table id="affi_list">
    <tr>
      <th>タイトル</th><th>数</th><th>fc1</th><th>lc1</th><th>bc1</th><th>bg1</th>
    </tr>
<?php
    $ptn_str = '/iframe src=\"(.+)?\"/i';
    foreach ($posts as $post) {
        preg_match_all( $ptn_str, $post['post_content'], $matches, PREG_SET_ORDER );
        $affi_count = count( $matches );

        $td_tag = '<td';
        if ( 1 < count($matches) ) {
            $td_tag .= ' rowspan="' . count($matches) . '"';
        }
        $td_tag .= '>';
        $match = array_shift( $matches );
?>
    <tr>
      <?php echo $td_tag; ?><a href="<?php echo $post['guid']; ?>"><?php echo $post['post_title']; ?></a></td>
      <?php echo $td_tag; ?><?php echo $affi_count; ?></td>
      <td><?php echo join('</td><td>', parse_color_code($match[1])); ?></td>
    </tr>
<?php
        foreach ($matches as $match) {
?>
    <tr>
      <td><?php echo join('</td><td>', parse_color_code($match[1])); ?></td>
    </tr>
<?php
        }
    }
?>
  </table>
<?php
}

function show_mgr_page(&$posts, $replaced = false) {
    $color_fc1 = array();
    $color_lc1 = array();
    $color_bc1 = array();
    $color_bg1 = array();

    $ptn_str = '/iframe src=\"(.+)?\"/i';
    foreach ($posts as $post) {
        preg_match_all( $ptn_str, $post['post_content'], $matches, PREG_SET_ORDER );
        foreach ($matches as $match) {
            $colors = parse_color_code( $match[1] );
            $color_fc1[$colors[0]]++;
            $color_lc1[$colors[1]]++;
            $color_bc1[$colors[2]]++;
            $color_bg1[$colors[3]]++;
        }
    }
?>
<?php if ( $replaced ) : ?>
  <p>一括置換されました（まだ未実装）</p>
<?php endif; ?>
  <form method="post" action="<?php echo $link_this_page; ?>">
  	<table class="aam_color">
      <tr><th> </th><th>変更前</th><th> </th><th>変更後</th></tr>
      <tr>
        <td class="aam_color_name"><label>テキストの色</label>(fc1)</td><td><?php echo join( ',', array_keys($color_fc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="fc1" id="fc1" value="" size="8" maxlength="6" /></td>
      </tr>
      <tr>
        <td class="aam_color_name"><label>リンクの色</label>(lc1)</td><td><?php echo join( ',', array_keys($color_lc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="lc1" id="lc1" value="" size="8" maxlength="6" /></td>
      </tr>
      <tr>
        <td class="aam_color_name"><label>ボーダーの色</label>(bc1)</td><td><?php echo join( ',', array_keys($color_bc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="bc1" id="bc1" value="" size="8" maxlength="6" /></td>
      </tr>
      <tr>
        <td class="aam_color_name"><label>背景の色</label>(bg1)</td><td><?php echo join( ',', array_keys($color_bg1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="bg1" id="bg1" value="" size="8" maxlength="6" /></td>
      </tr>
    </table>
    <input type="hidden" name="posted" value="Y">
    <p class="submit">
      <input type="submit" name="Submit" class="button-primary" value="一括置換" />
    </p>
  </form>
<?php
}

function parse_color_code($str) {
    preg_match( '/fc1=([0-9a-f]{6})?/i', $str, $m_fc );
    preg_match( '/lc1=([0-9a-f]{6})?/i', $str, $m_lc );
    preg_match( '/bc1=([0-9a-f]{6})?/i', $str, $m_bc );
    preg_match( '/bg1=([0-9a-f]{6})?/i', $str, $m_bg );

    return array(
        ( count($m_fc) ) ? $m_fc[1] : 'error',
        ( count($m_lc) ) ? $m_lc[1] : 'error',
        ( count($m_bc) ) ? $m_bc[1] : 'error',
        ( count($m_bg) ) ? $m_bg[1] : 'error'
    );
}
?>
