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

class AmazonAffiMgr {
    const AMAZON_URL = 'http://rcm-jp.amazon.co.jp/e/cm';

    public $posts = array();

    function __construct() {
        global $wpdb;
        $sql  = "SELECT * FROM $wpdb->posts";
        $sql .= " WHERE post_status = 'publish'";
        $sql .= " AND post_content LIKE '%" . AmazonAffiMgr::AMAZON_URL . "%'";
        $sql .= " ORDER BY ID DESC";
        $this->posts = $wpdb->get_results( $sql, ARRAY_A );
    }

    public function get_user_input(&$input, &$err_info) {
        foreach (array_keys($input) as $key) {
            if ( array_key_exists($key, $_POST) ) {
                $val = $_POST[$key];
                if ( preg_match('/[0-9a-f]{6}/i', $val) ) {
                    $input[$key] = $val; 
                }
                else {
                    // 不正だけどフォームに表示できる場合はそのままにしておく
                    $input[$key] = ( mb_strlen($val) <= 6 ) ? $val : '';
                    $err_info[$key] = ( $val === '' ) ? '未入力です' : 'フォーマットが不正です（例：000000）';
                }
            }
            else {
                // "フォームから送信している場合"は、ここには到達しない
            }
        }

        return ( count($err_info) == 0 );
    }

    public function exec_replace($input, $dryrun = true) {
        return array(
            'dryrun' => $dryrun,
            'count' => count($this->posts)
        );
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
    $ptn_str = '/iframe src=\"(.+?)\"/i';
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

function show_mgr_page(&$posts, $user_input, $err_info, $exec_result) {
    $color_fc1 = array();
    $color_lc1 = array();
    $color_bc1 = array();
    $color_bg1 = array();

    $ptn_str = '/iframe src=\"(.+?)\"/i';
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
<?php if ( $exec_result ) : ?>
  <section class="aam_result_info">
    <h3>実行結果（まだ未実装）</h3>
    <p>
<?php if ( $exec_result['dryrun'] ) : ?>
        テストモードなので、変更は反映されません。<br />
<?php endif; ?>
        <?php echo $exec_result['count']; ?>件の記事が更新されました。
    </p>
  </section>
<?php endif; ?>
<?php if ( $err_info ) : ?>
  <strong class="aam_error_info">入力に誤りがあります。</strong>
<?php endif; ?>
  <form method="post" action="<?php echo $link_this_page; ?>">
  	<table class="aam_color">
      <tr><th> </th><th>変更前</th><th> </th><th>変更後</th><th></th></tr>
      <tr>
        <td class="aam_color_name">テキストの色</td><td><?php echo join( ',', array_keys($color_fc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="fc1" id="fc1" value="<?php echo $user_input['fc1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('fc1', $err_info) ? $err_info['fc1'] : '&nbsp;' ); ?></td>
      </tr>
      <tr>
        <td class="aam_color_name">リンクの色</td><td><?php echo join( ',', array_keys($color_lc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="lc1" id="lc1" value="<?php echo $user_input['lc1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('lc1', $err_info) ? $err_info['lc1'] : '&nbsp;' ); ?></td>
      </tr>
      <tr>
        <td class="aam_color_name">ボーダーの色</td><td><?php echo join( ',', array_keys($color_bc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="bc1" id="bc1" value="<?php echo $user_input['bc1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('bc1', $err_info) ? $err_info['bc1'] : '&nbsp;' ); ?></td>
      </tr>
      <tr>
        <td class="aam_color_name">背景の色</td><td><?php echo join( ',', array_keys($color_bg1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="bg1" id="bg1" value="<?php echo $user_input['bg1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('bg1', $err_info) ? $err_info['bg1'] : '&nbsp;' ); ?></td>
      </tr>
    </table>
    <input type="hidden" name="posted" value="Y">
    <p class="submit">
      <input type="submit" name="submit" id="submit" class="button-primary" value="一括置換" /><input type="checkbox" name="dryrun" id="dryrun" value="1" checked="checked">テスト実行</input>
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

class AmazonAffiMgrView {
    function __construct() {
    }

    private function put_header() {
        echo '
<div class="wrap">
  <h2>Amazonアフィリエイトの管理</h2>';
    }

    private function put_footer() {
        echo '</div>';
    }

    private function put_menu(&$posts) {

        // このプラグインで追加したQUERY文字列を削除して、
        // このプラグインの管理画面のURIを作成
        $uri_this = str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] );
        $uri_this = str_replace( '&affi_list=1', '', $uri_this );

        // アフィリエイトを含む記事一覧ページのURIを作成
        $uri_list = $uri_this . '&affi_list=1';

        echo '
  <p>アフィリエイトを含む記事の数: ' . count($posts) . '件</p>
  <p>
    <a href="' . $uri_this . '">操作画面</a> / <a href="' . $uri_list . '">一覧を表示</a>
  </p>';
    }

    public function render(&$posts, $user_input, $err_info, $exec_result) {
        echo $this->put_header();
        if ( !$posts ) {
            show_post_not_exists();
        }
        else if ( $_GET['affi_list'] ) {
            echo $this->put_menu( $posts );
            show_affi_list( $posts );
        }
        else {
            echo $this->put_menu( $posts );
            show_mgr_page( $posts, $user_input, $err_info, $exec_result );
        }
        echo $this->put_footer();
    }
}

function amazon_affi_mgr_admin_page() {
    $mgr = new AmazonAffiMgr();

    $user_input = array( 'fc1' => '', 'lc1' => '', 'bc1' => '', 'bg1' => '' );

    $exec_result = array();
    if ( $mgr->posts && $_POST['posted'] === 'Y' ) {
        $err_info = array();
        if ( $mgr->get_user_input($user_input, $err_info) ) {
            $exec_result = $mgr->exec_replace( $user_input, $_POST['dryrun'] );
        }
    }

    $view = new AmazonAffiMgrView();
    $view->render( $mgr->posts, $user_input, $err_info, $exec_result );
}

?>
