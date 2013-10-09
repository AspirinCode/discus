Clazz.declarePackage ("J.viewer");
Clazz.load (["J.viewer.OutputManager"], "J.viewer.OutputManagerJS", ["J.io.JmolOutputChannel"], function () {
c$ = Clazz.declareType (J.viewer, "OutputManagerJS", J.viewer.OutputManager);
Clazz.makeConstructor (c$, 
function () {
Clazz.superConstructor (this, J.viewer.OutputManagerJS, []);
});
Clazz.overrideMethod (c$, "getLogPath", 
function (fileName) {
return fileName;
}, "~S");
Clazz.overrideMethod (c$, "clipImageOrPasteText", 
function (text) {
return "Clipboard not available";
}, "~S");
Clazz.overrideMethod (c$, "getClipboardText", 
function () {
return "Clipboard not available";
});
Clazz.overrideMethod (c$, "openOutputChannel", 
function (privateKey, fileName, asWriter, asAppend) {
return ( new J.io.JmolOutputChannel ()).setParams (this.viewer, fileName, asWriter, null);
}, "~N,~S,~B,~B");
Clazz.overrideMethod (c$, "createSceneSet", 
function (sceneFile, type, width, height) {
return "ERROR: Not Available";
}, "~S,~S,~N,~N");
});
