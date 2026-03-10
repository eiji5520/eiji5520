// XBoard API の型宣言（preloadで公開）
import type { XBoardAPI } from '../main/preload';

declare global {
  interface Window {
    xboard: XBoardAPI;
  }
}

export {};
