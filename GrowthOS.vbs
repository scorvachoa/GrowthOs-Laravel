Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "C:\laragon\www\growthos"

' Ejecutar php artisan serve (minimizado, sin ventana)
WshShell.Run "cmd /c cd /d C:\laragon\www\growthos && C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan serve", 0, False

' Esperar un momento para que arranque el servidor
WScript.Sleep 2000

' Ejecutar npm run dev (minimizado, sin ventana)
WshShell.Run "cmd /c cd /d C:\laragon\www\growthos && npm run dev", 0, False

' Esperar a que Vite arranque
WScript.Sleep 4000

' Abrir navegador
WshShell.Run "http://localhost:8000"

Set WshShell = Nothing
