// ============================================================
// XBoard - 一括操作エンジン
// 複数アカウントでの一括ポスト・いいね・リポスト等を実行
// ============================================================

import { BrowserWindow } from 'electron';
import { BulkOperationRequest, OperationLog, OperationType } from '../shared/types';
import { getSessionWindow } from './session-manager';
import { addLog } from './database';
import { v4 as uuidv4 } from 'crypto';

function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

/** スクリプトを各アカウントのウィンドウ内で実行 */
async function executeInWindow(win: BrowserWindow, script: string): Promise<any> {
  return win.webContents.executeJavaScript(script);
}

/** 遅延ユーティリティ */
function delay(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/** ポスト投稿スクリプト */
function getPostScript(text: string): string {
  // X のDOMを操作してポストを投稿するスクリプト
  return `
    (async () => {
      try {
        // ポスト入力欄をクリック
        const tweetBox = document.querySelector('[data-testid="tweetTextarea_0"]') ||
                          document.querySelector('[role="textbox"][data-testid]') ||
                          document.querySelector('div[contenteditable="true"][role="textbox"]');
        if (!tweetBox) throw new Error('ポスト入力欄が見つかりません');

        tweetBox.focus();
        document.execCommand('insertText', false, ${JSON.stringify(text)});

        await new Promise(r => setTimeout(r, 500));

        // 投稿ボタンをクリック
        const postBtn = document.querySelector('[data-testid="tweetButtonInline"]') ||
                        document.querySelector('[data-testid="tweetButton"]');
        if (!postBtn) throw new Error('投稿ボタンが見つかりません');

        postBtn.click();
        await new Promise(r => setTimeout(r, 1000));

        return { success: true };
      } catch (err) {
        return { success: false, error: err.message };
      }
    })();
  `;
}

/** いいねスクリプト */
function getLikeScript(postUrl: string): string {
  return `
    (async () => {
      try {
        window.location.href = ${JSON.stringify(postUrl)};
        await new Promise(r => setTimeout(r, 2000));

        const likeBtn = document.querySelector('[data-testid="like"]');
        if (!likeBtn) throw new Error('いいねボタンが見つかりません');

        likeBtn.click();
        await new Promise(r => setTimeout(r, 500));

        return { success: true };
      } catch (err) {
        return { success: false, error: err.message };
      }
    })();
  `;
}

/** リポストスクリプト */
function getRepostScript(postUrl: string): string {
  return `
    (async () => {
      try {
        window.location.href = ${JSON.stringify(postUrl)};
        await new Promise(r => setTimeout(r, 2000));

        const repostBtn = document.querySelector('[data-testid="retweet"]');
        if (!repostBtn) throw new Error('リポストボタンが見つかりません');

        repostBtn.click();
        await new Promise(r => setTimeout(r, 500));

        const confirmBtn = document.querySelector('[data-testid="retweetConfirm"]');
        if (confirmBtn) confirmBtn.click();

        await new Promise(r => setTimeout(r, 500));
        return { success: true };
      } catch (err) {
        return { success: false, error: err.message };
      }
    })();
  `;
}

/** ブックマークスクリプト */
function getBookmarkScript(postUrl: string): string {
  return `
    (async () => {
      try {
        window.location.href = ${JSON.stringify(postUrl)};
        await new Promise(r => setTimeout(r, 2000));

        const shareBtn = document.querySelector('[data-testid="bookmark"]') ||
                         document.querySelector('[aria-label="ブックマーク"]');
        if (!shareBtn) throw new Error('ブックマークボタンが見つかりません');

        shareBtn.click();
        await new Promise(r => setTimeout(r, 500));
        return { success: true };
      } catch (err) {
        return { success: false, error: err.message };
      }
    })();
  `;
}

/** フォロースクリプト */
function getFollowScript(targetUsername: string): string {
  return `
    (async () => {
      try {
        window.location.href = 'https://x.com/${targetUsername}';
        await new Promise(r => setTimeout(r, 2000));

        const followBtn = document.querySelector('[data-testid*="follow"]') ||
                          Array.from(document.querySelectorAll('div[role="button"]'))
                            .find(btn => btn.textContent?.includes('フォロー'));
        if (!followBtn) throw new Error('フォローボタンが見つかりません');

        followBtn.click();
        await new Promise(r => setTimeout(r, 500));
        return { success: true };
      } catch (err) {
        return { success: false, error: err.message };
      }
    })();
  `;
}

export interface BulkOperationProgress {
  total: number;
  completed: number;
  succeeded: number;
  failed: number;
  logs: OperationLog[];
}

/** 一括操作を実行 */
export async function executeBulkOperation(
  request: BulkOperationRequest,
  onProgress?: (progress: BulkOperationProgress) => void,
): Promise<BulkOperationProgress> {
  const progress: BulkOperationProgress = {
    total: request.accountIds.length,
    completed: 0,
    succeeded: 0,
    failed: 0,
    logs: [],
  };

  const delayMs = request.delayMs || 2000;

  for (const accountId of request.accountIds) {
    const win = getSessionWindow(accountId);
    const log: OperationLog = {
      id: generateId(),
      accountId,
      type: request.type as OperationType,
      target: request.targetUrl || request.targetUsername || undefined,
      status: 'pending',
      timestamp: Date.now(),
    };

    if (!win) {
      log.status = 'failure';
      log.message = 'セッションが開かれていません';
      progress.failed++;
    } else {
      try {
        let script: string;
        switch (request.type) {
          case 'post':
            script = getPostScript(request.content || '');
            break;
          case 'like':
            script = getLikeScript(request.targetUrl || '');
            break;
          case 'repost':
            script = getRepostScript(request.targetUrl || '');
            break;
          case 'bookmark':
            script = getBookmarkScript(request.targetUrl || '');
            break;
          case 'follow':
            script = getFollowScript(request.targetUsername || '');
            break;
          default:
            throw new Error(`未対応の操作タイプ: ${request.type}`);
        }

        const result = await executeInWindow(win, script);
        if (result?.success) {
          log.status = 'success';
          progress.succeeded++;
        } else {
          log.status = 'failure';
          log.message = result?.error || '不明なエラー';
          progress.failed++;
        }
      } catch (err: any) {
        log.status = 'failure';
        log.message = err.message;
        progress.failed++;
      }
    }

    progress.completed++;
    progress.logs.push(log);
    addLog(log);

    onProgress?.(progress);

    // 操作間の遅延
    if (progress.completed < progress.total) {
      await delay(delayMs);
    }
  }

  return progress;
}
