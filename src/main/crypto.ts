// ============================================================
// XBoard - 暗号化モジュール
// ローカルデータの暗号化・復号化を行う
// ============================================================

import * as crypto from 'crypto';
import * as fs from 'fs';
import * as path from 'path';
import { app } from 'electron';
import {
  ENCRYPTION_ALGORITHM,
  ENCRYPTION_KEY_LENGTH,
  ENCRYPTION_IV_LENGTH,
  ENCRYPTION_TAG_LENGTH,
} from '../shared/constants';

const KEY_FILE = 'xboard.key';

/** マスターキーを取得または生成 */
function getMasterKey(): Buffer {
  const keyPath = path.join(app.getPath('userData'), KEY_FILE);

  if (fs.existsSync(keyPath)) {
    return Buffer.from(fs.readFileSync(keyPath, 'utf-8'), 'hex');
  }

  const key = crypto.randomBytes(ENCRYPTION_KEY_LENGTH);
  fs.writeFileSync(keyPath, key.toString('hex'), { mode: 0o600 });
  return key;
}

let cachedKey: Buffer | null = null;

function getKey(): Buffer {
  if (!cachedKey) {
    cachedKey = getMasterKey();
  }
  return cachedKey;
}

/** テキストを暗号化 */
export function encrypt(plaintext: string): string {
  const key = getKey();
  const iv = crypto.randomBytes(ENCRYPTION_IV_LENGTH);
  const cipher = crypto.createCipheriv(ENCRYPTION_ALGORITHM, key, iv) as crypto.CipherGCM;

  let encrypted = cipher.update(plaintext, 'utf-8', 'hex');
  encrypted += cipher.final('hex');

  const tag = cipher.getAuthTag();

  // iv:tag:encrypted の形式で返す
  return `${iv.toString('hex')}:${tag.toString('hex')}:${encrypted}`;
}

/** 暗号化テキストを復号化 */
export function decrypt(encryptedData: string): string {
  const key = getKey();
  const parts = encryptedData.split(':');

  if (parts.length !== 3) {
    throw new Error('不正な暗号化データ形式');
  }

  const iv = Buffer.from(parts[0], 'hex');
  const tag = Buffer.from(parts[1], 'hex');
  const encrypted = parts[2];

  const decipher = crypto.createDecipheriv(ENCRYPTION_ALGORITHM, key, iv) as crypto.DecipherGCM;
  decipher.setAuthTag(tag);

  let decrypted = decipher.update(encrypted, 'hex', 'utf-8');
  decrypted += decipher.final('utf-8');

  return decrypted;
}

/** ファイルを暗号化して保存 */
export function encryptFile(filePath: string, data: string): void {
  const encrypted = encrypt(data);
  fs.writeFileSync(filePath, encrypted, 'utf-8');
}

/** 暗号化ファイルを読み込み復号化 */
export function decryptFile(filePath: string): string {
  const encrypted = fs.readFileSync(filePath, 'utf-8');
  return decrypt(encrypted);
}

/** パスワードをハッシュ化 */
export function hashPassword(password: string): string {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(password, salt, 64).toString('hex');
  return `${salt}:${hash}`;
}

/** パスワード検証 */
export function verifyPassword(password: string, stored: string): boolean {
  const [salt, hash] = stored.split(':');
  const derivedHash = crypto.scryptSync(password, salt, 64).toString('hex');
  return crypto.timingSafeEqual(Buffer.from(hash, 'hex'), Buffer.from(derivedHash, 'hex'));
}
