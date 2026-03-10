; XBoard NSIS カスタムインストーラー設定
; electron-builder の nsis.include で読み込まれる

!macro customHeader
  !system "echo 'XBoard Installer'"
!macroend

!macro customInit
  ; 最小メモリチェック（推奨8GB）
  ; 注: NSISではRAMチェックは複雑なため、ここではスキップ
!macroend

!macro customInstall
  ; デスクトップにショートカットを作成
  CreateShortCut "$DESKTOP\XBoard.lnk" "$INSTDIR\XBoard.exe" "" "$INSTDIR\XBoard.exe" 0

  ; スタートメニューにショートカットを作成
  CreateDirectory "$SMPROGRAMS\XBoard"
  CreateShortCut "$SMPROGRAMS\XBoard\XBoard.lnk" "$INSTDIR\XBoard.exe" "" "$INSTDIR\XBoard.exe" 0
  CreateShortCut "$SMPROGRAMS\XBoard\アンインストール.lnk" "$INSTDIR\Uninstall XBoard.exe"
!macroend

!macro customUnInstall
  ; ショートカットを削除
  Delete "$DESKTOP\XBoard.lnk"
  RMDir /r "$SMPROGRAMS\XBoard"
!macroend
