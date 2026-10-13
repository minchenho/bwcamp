/**
 * 設定全域變數，必須是 bootstrap.js 第一個 import 的模組。
 *
 * ES module 的 import 會被提升，並依照 import 的順序逐一執行。
 * 把全域設定獨立成一個檔案並最先 import，
 * 才能保證後面的 bootstrap、./confirmation 執行時已經有 window.jQuery 和 window.Popper。
 */
import _ from 'lodash';
import $ from 'jquery';
import Popper from './popover';

window._ = _;
window.$ = $;
window.jQuery = $;
window.Popper = Popper;