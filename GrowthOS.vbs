Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "C:\laragon\www\growthos"

' Verificar si MySQL esta corriendo
Set objWMIService = GetObject("winmgmts:\\.\root\cimv2")
Set colProcesses = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE Name = 'mysqld.exe'")

If colProcesses.Count = 0 Then
    ' MySQL no esta corriendo, intentar iniciar Laragon
    Set fso = CreateObject("Scripting.FileSystemObject")
    
    ' Buscar Laragon en las ubicaciones comunes
    laragonPaths = Array("C:\laragon\laragon.exe", "C:\Program Files\Laragon\laragon.exe")
    laragonFound = False
    
    For Each path In laragonPaths
        If fso.FileExists(path) Then
            WshShell.Run """" & path & """", 1, False
            laragonFound = True
            ' Esperar a que MySQL arranque
            WScript.Sleep 8000
            Exit For
        End If
    Next
    
    If Not laragonFound Then
        MsgBox "No se encontro Laragon. Por favor, inicia Laragon manualmente.", vbExclamation, "GrowthOS"
    End If
End If

' Verificar si PHP ya esta corriendo (evitar duplicados)
Set colPHP = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE CommandLine LIKE '%artisan serve%'")
If colPHP.Count = 0 Then
    ' Iniciar php artisan serve (minimizado)
    WshShell.Run "cmd /c cd /d C:\laragon\www\growthos && C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan serve --host=127.0.0.1 --port=8000", 0, False
    WScript.Sleep 2000
End If

' Verificar si npm ya esta corriendo
Set colNPM = objWMIService.ExecQuery("SELECT * FROM Win32_Process WHERE CommandLine LIKE '%npm run dev%'")
If colNPM.Count = 0 Then
    ' Iniciar npm run dev (minimizado)
    WshShell.Run "cmd /c cd /d C:\laragon\www\growthos && npm run dev", 0, False
    WScript.Sleep 5000
End If

' Abrir navegador
WshShell.Run "http://localhost:8000"

Set WshShell = Nothing
