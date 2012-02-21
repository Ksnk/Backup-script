@echo off
rem 
rem   you can use batch file for building Backup-script
rem     build.bat  without parameters compile all target
rem     build.bat allinone - just to build only one target
rem 
rem 
rem   Make shure, this is correct path to you PHP interpreter and main preprocessor file
rem 
if "%PHPBIN%" == ""  set PHPBIN=Z:\usr\local\php5\php.exe
if "%PROCESSOR%"=="" set PROCESSOR=utils\preprocessor\preprocessor.php 

rem 
rem   so let's go!
rem 

if not exist "%PROCESSOR%" goto no_preprocessor

if not "%1"=="" goto next

rem 
rem  target to make all at once
rem 
set PAR=allinone,cms-plugin

for %%i in (%PAR%) do call :%%i

goto fin


:next
if "%1"=="" goto fin
call :%1

shift
goto next

rem
rem You can place your targets here
rem


:allinone

echo building allinone
%PHPBIN% -q  %PROCESSOR% /Ddst=build/allinone /Dtarget=allinone /P=svn.prop ^
	/Dexecutor=URI_executor config.xml
exit /b 0


:cms-plugin

echo building cms-plugin
%PHPBIN% -q  %PROCESSOR% /Ddst=build/cms-plugin /Dtarget=cms-plugin /P=svn.prop ^
	/Dexecutor=empty_executor config.xml
exit /b 0


:no_preprocessor
echo Preprocessor file not found (%PROCESSOR%). 
goto fin

:fin