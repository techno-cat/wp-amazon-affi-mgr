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
    wp_enqueue_style( 'amazon_affi_mgr', $my_css );
}
add_action( 'admin_init', 'amazon_affi_mgr_add_css' );

function amazon_affi_mgr_del_wpmp_css() {
    if ( wp_style_is('wpmp-admin-custom') ) {
        wp_dequeue_style( 'wpmp-admin-custom' );
    }
}
add_action( 'admin_enqueue_scripts', 'amazon_affi_mgr_del_wpmp_css', 100 );

class AmazonAffiMgr {
    const AMAZON_URL = 'http://rcm-jp.amazon.co.jp/e/cm';
    const PREG_AFFI_PTN = '/iframe src=\"(.+?)\"/i';

    public $posts = array();
    public $user_input = array( 'fc1' => '', 'lc1' => '', 'bc1' => '', 'bg1' => '' );
    public $error_info = array();
    public $exec_result = array();

    function __construct() {
        global $wpdb;
        $sql  = "SELECT * FROM $wpdb->posts";
        $sql .= " WHERE post_status = 'publish'";
        $sql .= " AND post_content LIKE '%" . AmazonAffiMgr::AMAZON_URL . "%'";
        $sql .= " ORDER BY ID DESC";
        $this->posts = $wpdb->get_results( $sql, ARRAY_A );

        if ( $this->posts && $_POST['posted'] === 'Y' ) {
            $this->update_user_input();
            if ( !$this->error_info ) {
                $this->exec_replace( $_POST['dryrun'] );
            }
        }
    }

    private function update_user_input() {
        foreach (array_keys($this->user_input) as $key) {
            if ( array_key_exists($key, $_POST) ) {
                $val = $_POST[$key];
                if ( preg_match('/[0-9a-f]{6}/i', $val) ) {
                    $this->user_input[$key] = $val; 
                }
                else {
                    // 不正だけどフォームに表示できる場合はそのままにしておく
                    $this->user_input[$key] = ( mb_strlen($val) <= 6 ) ? $val : '';
                    $this->error_info[$key] = ( $val === '' ) ? '未入力です' : 'フォーマットが不正です（例：000000）';
                }
            }
            else {
                // "フォームから送信している場合"は、ここには到達しない
            }
        }
    }

    public function replace_color($post_content) {
        $ptn_str = AmazonAffiMgr::PREG_AFFI_PTN;

        preg_match_all( $ptn_str, $post_content, $matches, PREG_SET_ORDER );
        foreach ($matches as $match) {

            // 非効率だけど、iframeタグ単位で置換する
            $affi = $match[1];
            foreach(array_keys($this->user_input) as $key) {
                if ( preg_match(('/' . $key . '=([0-9a-f]{6})?/i'), $affi, $tmp) ) {
                    $affi = str_replace( ($key . '=' . $tmp[1]),  ($key . '=' . $this->user_input[$key]), $affi );
                }
            }

            $post_content = str_replace( $match[1], $affi, $post_content );
        }

        return $post_content;
    }

    public function exec_replace($dryrun = true) {

        $log = '';
        if ( $dryrun ) {
            $post   = $this->posts[0];
            $before = $post['post_content'];
            $after  = $this->replace_color( $before );

            $ptn_str = '/(<iframe.*\/iframe>)?/i';
 
            $log = '<strong>変更前</strong><p>';
            preg_match_all( $ptn_str, $before, $matches, PREG_SET_ORDER );
            foreach ($matches as $match) {
                $log .= $match[1];
            }
            $log .= '</p>';

            $log .= '<strong>変更後</strong><p>';
            preg_match_all( $ptn_str, $after, $matches, PREG_SET_ORDER );
            foreach ($matches as $match) {
                $log .= $match[1];
            }
            $log .= '</p>';
        }

        $count = 0;
        $error_posts = array();
        foreach ($this->posts as $post) {

            $post['post_content'] = $this->replace_color( $post['post_content'] );

            // データベースを更新
            if ( !$dryrun ) {
                $new_post = array();
                $new_post['ID'] = $post['ID'];
                $new_post['post_content'] = $post['post_content'];

                if ( wp_update_post($new_post) == 0 ) {
                    // データベース更新エラー
                    $error_posts[] = $post; 
                }
            }

            $count++;
        }

        $this->exec_result = array(
            'dryrun' => $dryrun,
            'count'  => $count,
            'log'    => $log,
            'error'  => $error_posts
        );
    }
}

function aam_show_post_not_exists() {
?>
  <p>アフィリエイトを含む記事はみつかりませんでした。</p>
<?php
}    
    
function aam_show_affi_list(&$posts) {
?>

  <table id="affi_list">
    <tr>
      <th>タイトル</th><th>数</th><th>fc1</th><th>lc1</th><th>bc1</th><th>bg1</th>
    </tr>
<?php
    $ptn_str = AmazonAffiMgr::PREG_AFFI_PTN;
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

function aam_show_exec_result($exec_result) {
?>
  <section class="aam_result_info">
    <h3>実行結果</h3>
    <p>
<?php if ( $exec_result['dryrun'] ) : ?>
        お試しモードなので、変更は反映されません。<br />
<?php endif; ?>
        <?php echo $exec_result['count']; ?>件の記事が更新されました。
    </p>
<?php if ( $exec_result['log'] ) : ?>
    <p><?php echo $exec_result['log']; ?>
<?php endif; ?>
<?php if ( $exec_result['error'] ) : ?>
    <p><strong>変更が反映されなかった記事一覧</strong></p>
    <table id="affi_list">
      <tr><th>タイトル</th></tr>
<?php
    foreach ($exec_result['error'] as $post) {
?>
      <tr><td><a href="<?php echo $post['guid']; ?>"><?php echo $post['post_title']; ?></a></td></tr>
<?php
    }
?>
    </table>
<?php endif; ?>
  </section>
<?php
}

function aam_show_test_result(&$mgr) {

    // フォームに正しい値が入力されたことにする
    $user_input = array(
        'fc1' => '123456',
        'lc1' => '789ABC',
        'bc1' => 'DEF123',
        'bg1' => '456789',
    );

    $post = $mgr->posts[0];
    $original_constent = $post['post_content'];
    $ptn_str = AmazonAffiMgr::PREG_AFFI_PTN;

    preg_match_all( $ptn_str, $post['post_content'], $matches, PREG_SET_ORDER );
    
    echo '<h3>同じ文字列に置換するテスト</h3>';
    echo '<table class="aam_test">';
    $replaced_constent = $original_constent;
    foreach ($matches as $match) {
        $got = $match[1];
        foreach(array_keys($user_input) as $key) {
            if ( preg_match(('/' . $key . '=([0-9a-f]{6})?/i'), $got, $tmp) ) {
                $cnt = 0;
                $got = str_replace( ($key . '=' . $tmp[1]),  ($key . '=' . $tmp[1]), $got, $cnt );
                echo '<tr><td>' . $key . 'の置換</td><td>' . (($cnt == 1) ? 'OK' : 'NG') . '</td></tr>';
            }
        }

        echo '<tr><td>src属性の比較</td><td>' . (($got === $match[1]) ? 'OK' : 'NG') . '</td></tr>';

        $cnt = 0;
        $replaced_constent = str_replace( $match[1], $got,  $replaced_constent, $cnt );
        echo '<tr><td>src属性の置換</td><td>' . (($cnt == 1) ? 'OK' : 'NG') . '</td></tr>';
    }

    $cmp_ok = ( $replaced_constent === $original_constent );
    echo '<tr><td>投稿内容の比較</td><td>' . ($cmp_ok ? 'OK' : 'NG') . '</td></tr>';
    echo '</table>';

    echo '<h3>目視確認の出力</h3>';
    echo '<p>テスト用の入力内容</p>';
    echo '<table class="aam_test">';
    foreach(array_keys($user_input) as $key) {
        echo '<tr><td>' . $key . '=' . $user_input[$key] . '</td></tr>';
    }
    echo '</table><br />';

    echo '<table class="aam_test">';
    $replaced_constent = $original_constent;
    foreach ($matches as $match) {
        $got = $match[1];
        foreach(array_keys($user_input) as $key) {
            if ( preg_match(('/' . $key . '=([0-9a-f]{6})?/i'), $got, $tmp) ) {
                $got = str_replace( ($key . '=' . $tmp[1]),  ($key . '=' . $user_input[$key]), $got );
                echo '<tr><td>' . $key . 'の置換</td><td>' . (($cnt == 1) ? 'OK' : 'NG') . '</td></tr>';
            }
        }

        foreach(array_keys($user_input) as $key) {
            preg_match( ('/' . $key . '=([0-9a-f]{6})?/i'), $match[1], $tmp );
            $str_before = $key . '=' . $tmp[1];
            preg_match( ('/' . $key . '=([0-9a-f]{6})?/i'), $got,      $tmp );
            $str_after = $key . '=' . $tmp[1];
            echo '<tr><td>目視確認</td><td>' . $str_before . ' -&gt; ' . $str_after . '</td></tr>';
        }

        if ( mb_strlen($got) === mb_strlen($match[1]) ) {
            echo '<tr><td>文字数の比較</td><td>OK</td></tr>';
        }
        else {
            echo '<tr><td>文字数の比較</td><td>NG</td></tr>';
        }

        $replaced_constent = str_replace( $match[1], $got,  $replaced_constent, $cnt );
        echo '<tr><td>src属性の置換</td><td>' . (($cnt == 1) ? 'OK' : 'NG') . '</td></tr>';
    }
    echo '<tr><td>replace_colorのテスト</td><td>';

    $mgr->user_input = $user_input;
    echo ( $mgr->replace_color($original_constent) === $replaced_constent ) ? 'OK' : 'NG'; 

    echo '</td></tr>';
    echo '</table>';
}

function aam_show_mgr_page(&$posts, $user_input, $err_info) {
    $color_fc1 = array();
    $color_lc1 = array();
    $color_bc1 = array();
    $color_bg1 = array();

    $ptn_str = AmazonAffiMgr::PREG_AFFI_PTN;
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
<?php if ( $err_info ) : ?>
  <strong class="aam_error_info">入力に誤りがあります。</strong>
<?php endif; ?>
  <form method="post" action="<?php echo $link_this_page; ?>">
  	<table class="aam_color">
      <tr><th> </th><th>変更前</th><th> </th><th>変更後</th><th></th></tr>
      <tr>
        <td class="aam_color_name">テキストの色</td><td><?php echo join( ', ', array_keys($color_fc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="fc1" id="fc1" value="<?php echo $user_input['fc1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('fc1', $err_info) ? $err_info['fc1'] : '&nbsp;' ); ?></td>
      </tr>
      <tr>
        <td class="aam_color_name">リンクの色</td><td><?php echo join( ', ', array_keys($color_lc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="lc1" id="lc1" value="<?php echo $user_input['lc1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('lc1', $err_info) ? $err_info['lc1'] : '&nbsp;' ); ?></td>
      </tr>
      <tr>
        <td class="aam_color_name">ボーダーの色</td><td><?php echo join( ', ', array_keys($color_bc1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="bc1" id="bc1" value="<?php echo $user_input['bc1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('bc1', $err_info) ? $err_info['bc1'] : '&nbsp;' ); ?></td>
      </tr>
      <tr>
        <td class="aam_color_name">背景の色</td><td><?php echo join( ', ', array_keys($color_bg1)); ?></td><td>&gt;&gt;</td>
        <td><input type="text" name="bg1" id="bg1" value="<?php echo $user_input['bg1']; ?>" size="8" maxlength="6" /></td>
        <td><?php echo ( array_key_exists('bg1', $err_info) ? $err_info['bg1'] : '&nbsp;' ); ?></td>
      </tr>
    </table>
    <input type="hidden" name="posted" value="Y">
    <p class="submit">
      <input type="submit" name="submit" id="submit" class="button-primary" value="一括置換" /><input type="checkbox" name="dryrun" id="dryrun" value="1" checked="checked">お試しモード</input>
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
        $uri_this = str_replace( '&affi_test=1', '', $uri_this );

        // アフィリエイトを含む記事一覧ページのURIを作成
        $uri_list = $uri_this . '&affi_list=1';

        // デバッグ作業用（置換処理の結果を出力する）のURIを作成
        $url_test = $uri_this . '&affi_test=1';

        echo '
  <p>アフィリエイトを含む記事の数: ' . count($posts) . '件</p>
  <p>
    <a href="' . $uri_this . '">操作画面</a> / <a href="' . $uri_list . '">一覧を表示</a> / <a href="' . $url_test . '">テスト</a>
  </p>';
    }

    public function render(&$mgr) {
        echo $this->put_header();
        if ( !$mgr->posts ) {
            aam_show_post_not_exists();
        }
        else if ( $_GET['affi_list'] ) {
            echo $this->put_menu( $mgr->posts );
            aam_show_affi_list( $mgr->posts );
        }
        else if ( $_GET['affi_test'] ) {
            echo $this->put_menu( $mgr->posts );
            aam_show_test_result( $mgr );
        }
        else {
            echo $this->put_menu( $mgr->posts );
            if ( $mgr->exec_result ) {
                aam_show_exec_result( $mgr->exec_result );
            }
            aam_show_mgr_page( $mgr->posts, $mgr->user_input, $mgr->error_info );
        }
        echo $this->put_footer();
    }
}

function amazon_affi_mgr_admin_page() {
    $mgr = new AmazonAffiMgr();
    $view = new AmazonAffiMgrView();
    $view->render( $mgr );
}

?>
