var whiteColor = new SolidColor();
whiteColor.rgb.red = 255;
whiteColor.rgb.green = 255;
whiteColor.rgb.blue = 255;

var red = new SolidColor();
red.rgb.red = 255;
red.rgb.green = 0;
red.rgb.blue = 0;

var green = new SolidColor();
green.rgb.red = 0;
green.rgb.green = 255;
green.rgb.blue = 0;

var blue = new SolidColor();
blue.rgb.red = 0;
blue.rgb.green = 0;
blue.rgb.blue = 255;

var yellow = new SolidColor();
yellow.rgb.red = 255;
yellow.rgb.green = 255;
yellow.rgb.blue = 0;

var pink = new SolidColor();
pink.rgb.red = 255;
pink.rgb.green = 105;
pink.rgb.blue = 180;

var black = new SolidColor();
black.rgb.red = 0;
black.rgb.green = 0;
black.rgb.blue = 0;



var color_converter = {
    'Weiß': whiteColor,
    'Grün': green,
    'Rot' : red,
    'Blau': blue,
    'Gelb': yellow,
    'Rosa': pink,
    'Schwarz':black,
    'Pink':pink
}
fonts_converter = {}

for(f=0;f<app.fonts.length;f++){
    if(app.fonts[f].family in fonts_converter){
        if(app.fonts[f].style =='Regular'){
            fonts_converter[app.fonts[f].family] = app.fonts[f].postScriptName
        }
    }
    else{
        
        fonts_converter[app.fonts[f].family] = app.fonts[f].postScriptName
    }
    
}

var w = new Window('dialog','Feuerzeug Buchstabe')
    w.alignChildren = 'left'
    w.add('statictext',undefined,'Select folder contains template')
    g1 = w.add('group')
        ebox1 = g1.add('edittext',undefined,'');ebox1.characters = '45';
        button1 = g1.add('button',[0,0,25,25],'...')


    w.add('statictext',undefined,'Select csv file:')
    g2 = w.add('group')
        ebox2 = g2.add('edittext',undefined,'');ebox2.characters = '45';
        button2 = g2.add('button',[0,0,25,25],'...')

    w.add('statictext',undefined,'Select Output PSDs file:')
    g3 = w.add('group')
        ebox3 = g3.add('edittext',undefined,'');ebox3.characters = '45';
        button3 = g3.add('button',[0,0,25,25],'...')

    g4 = w.add('group')
        ok = g4.add('button',undefined,'OK')
        cancel = g4.add('button',undefined,'Cancel')

    button1.onClick = function(){
        ebox1.text = Folder.selectDialog()
    }
    button2.onClick = function(){
        ebox2.text = File.openDialog().fullName
    }
    button3.onClick = function(){
        ebox3.text = Folder.selectDialog().fullName
    }
result = w.show()


if (result==true){
    template_files = Folder(ebox1.text).getFiles('*.psd')
    index = "not found"
    for(f = 0; f < template_files.length; f++){
        if(template_files[f].displayName.search('Feuerzeug Buchstabe')!=-1){
            index = f
            break
        }
    }
    if(index == "not found"){
        alert('Feuerzeug Buchstabe.psd not found')
        //return is not working as it is not in a function, pls fix
    }
    else{
        data = read_csv(ebox2.text)
        output_folder = ebox3.text+'/'
        run(template_files[index])
    }
}

function run(template_file){
    template = app.open(template_file)
    layers = template.layers
    file_count = 1
    box_texts = [] // Array to collect all non-empty box_text values

    for (i=0; i < data.length; i++){
        // Extract data for this iteration
        letter_color = data[i][0];
        letter       = data[i][1];
        text         = data[i][2];
        font         = data[i][3];
        box_text     = data[i][4];


        // 1) Find text layer by exact name = font, remove all other text layers, make it visible and set its text
        var textLayer = findTextLayerByName(template, font);
        if (textLayer) {
            // Remove all other text layers before making this one visible
            removeAllOtherTextLayers(template, textLayer);
            textLayer.visible = true;
            if (textLayer.kind == LayerKind.TEXT) {
                textLayer.textItem.contents = text;
            }
        }

        // 2) Find LayerSet by letter_color, then find layer named = letter inside it and make visible
        var colorSet = findLayerSetByName(template, letter_color);
        if (colorSet) {
            var letterLayer = findLayerInSetByName(colorSet, letter);
            if (letterLayer) {
                letterLayer.visible = true;
                
                // 2.1) Remove all other layers inside the colorSet (keep only letterLayer)
                // removeOtherLayersInSet(colorSet, letterLayer);
            }
            
            // 2.2) Remove all other color LayerSets (keep only the selected colorSet)
            removeOtherColorSets(template, colorSet);
        }
        //2.3) Create W1 layer
        // create_w1()

        // 2.4) Collect box_text if not empty
        if (box_text != '"' || box_text != '' ) {
            box_texts.push(box_text.replace('"',''));
        }

        // 2.5) Remove all invisible layers before saving
        // merge_visible()
        // removeInvisibleLayers(template);

        // 3) Save current document to output folder
        template.saveAs(new File(output_folder + 'Feuerzeug Buchstabe_' + file_count + '.psd'));
        app.activeDocument.close();
        file_count += 1;

        // 4) If not the last item, reopen the original template and reset references
        if (i < data.length - 1) {
            template = app.open(template_file);
            layers = template.layers;
        }
    }
    
    // 5) Write all box_text values to a text file if any exist
    // Filter out empty lines before writing
    var filteredBoxTexts = [];
    for (var k = 0; k < box_texts.length; k++) {
        var text = box_texts[k];
        // Remove whitespace manually (ExtendScript doesn't have trim())
        if (text) {
            text = text.replace(/^\s+|\s+$/g, ''); // Remove leading and trailing whitespace
            if (text != '') {
                filteredBoxTexts.push(box_texts[k]); // Push original, not trimmed version
            }
        }
    }
    
    if (filteredBoxTexts.length > 0) {
        var txtFile = new File(output_folder + 'box_texts.txt');
        txtFile.open('w');
        for (var j = 0; j < filteredBoxTexts.length; j++) {
            txtFile.write(filteredBoxTexts[j] + '\n');
        }
        txtFile.close();
    }
}

// Remove all text layers except the one to keep (recursively through LayerSets)
function removeAllOtherTextLayers(container, keepLayer) {
    if (!keepLayer) return;
    
    var keepLayerName = keepLayer.name.toLowerCase();
    var layers = container.layers;
    // Iterate backwards to safely delete while iterating
    for (var i = layers.length - 1; i >= 0; i--) {
        var lyr = layers[i];
        if (lyr.typename == "ArtLayer" && lyr.kind == LayerKind.TEXT) {
            // Remove if it's not the layer we want to keep (compare by name, case-insensitive)
            if (lyr.name.toLowerCase() !== keepLayerName) {
                lyr.remove();
            }
        } else if (lyr.typename == "LayerSet") {
            // Recursively process LayerSets
            removeAllOtherTextLayers(lyr, keepLayer);
        }
    }
}

// Find a text layer (any depth) in the document whose name matches `layerName` (case-insensitive)
function findTextLayerByName(doc, layerName) {
    var searchName = layerName.toLowerCase();
    for (var i = 0; i < doc.layers.length; i++) {
        var lyr = doc.layers[i];
        if (lyr.typename == "ArtLayer") {
            if (lyr.name.toLowerCase() == searchName && lyr.kind == LayerKind.TEXT) {
                return lyr;
            }
        } else if (lyr.typename == "LayerSet") {
            var found = findTextLayerByName(lyr, layerName);
            if (found) return found;
        }
    }
    return null;
}

// Overload to allow searching within a LayerSet as well (case-insensitive)
function findTextLayerByName(layerSet, layerName) {
    var searchName = layerName.toLowerCase();
    for (var i = 0; i < layerSet.layers.length; i++) {
        var lyr = layerSet.layers[i];
        if (lyr.typename == "ArtLayer") {
            if (lyr.name.toLowerCase() == searchName && lyr.kind == LayerKind.TEXT) {
                return lyr;
            }
        } else if (lyr.typename == "LayerSet") {
            var found = findTextLayerByName(lyr, layerName);
            if (found) return found;
        }
    }
    return null;
}

// Find a top-level LayerSet (or nested, if called on a LayerSet) by name (case-insensitive)
function findLayerSetByName(container, setName) {
    var searchName = setName.toLowerCase();
    var layers = container.layers ? container.layers : container.layerSets;
    for (var i = 0; i < layers.length; i++) {
        var lyr = layers[i];
        if (lyr.typename == "LayerSet") {
            if (lyr.name.toLowerCase() == searchName) {
                return lyr;
            }
            var found = findLayerSetByName(lyr, setName);
            if (found) return found;
        }
    }
    return null;
}

// Find a layer (any depth) inside a given LayerSet by name (case-insensitive)
function findLayerInSetByName(layerSet, layerName) {
    var searchName = layerName.toLowerCase();
    for (var i = 0; i < layerSet.layers.length; i++) {
        var lyr = layerSet.layers[i];
        if (lyr.typename == "ArtLayer" && lyr.name.toLowerCase() == searchName) {
            return lyr;
        } else if (lyr.typename == "LayerSet") {
            var found = findLayerInSetByName(lyr, layerName);
            if (found) return found;
        }
    }
    return null;
}

// Remove all other layers inside a LayerSet except the specified layer (case-insensitive)
function removeOtherLayersInSet(layerSet, keepLayer) {
    if (!keepLayer) return; // Safety check: if no layer to keep, don't remove anything
    
    var keepLayerName = keepLayer.name.toLowerCase();
    var layers = layerSet.layers;
    // Iterate backwards to safely delete while iterating
    for (var i = layers.length - 1; i >= 0; i--) {
        var lyr = layers[i];
        // Compare by name (case-insensitive) instead of reference, and also check if it's the same type
        if (lyr.name.toLowerCase() !== keepLayerName || lyr.typename !== keepLayer.typename) {
            lyr.remove();
        }
    }
}

// Find the parent container of a layer/layerSet
function findParentContainer(doc, targetLayer) {
    function searchParent(container, target, parent) {
        var layers = container.layers;
        for (var i = 0; i < layers.length; i++) {
            var lyr = layers[i];
            if (lyr === target) {
                return parent || doc;
            }
            if (lyr.typename == "LayerSet") {
                var found = searchParent(lyr, target, lyr);
                if (found !== null) return found;
            }
        }
        return null;
    }
    return searchParent(doc, targetLayer, null);
}

// Remove all other color LayerSets at the same level as the selected colorSet
function removeOtherColorSets(doc, keepColorSet) {
    var parent = findParentContainer(doc, keepColorSet);
    if (!parent) return;
    
    var layers = parent.layers;
    // Iterate backwards to safely delete while iterating
    for (var i = layers.length - 1; i >= 0; i--) {
        var lyr = layers[i];
        if (lyr.typename == "LayerSet" && lyr !== keepColorSet) {
            lyr.remove();
        }
    }
}

// Remove all invisible layers (recursively through LayerSets)
function removeInvisibleLayers(container) {
    var layers = container.layers;
    // Iterate backwards to safely delete while iterating
    for (var i = layers.length - 1; i >= 0; i--) {
        var lyr = layers[i];
        if (!lyr.visible) {
            lyr.remove();
        } else if (lyr.typename == "LayerSet") {
            // Recursively remove invisible layers from LayerSets
            removeInvisibleLayers(lyr);
            // If LayerSet is now empty, remove it
            if (lyr.layers.length == 0) {
                lyr.remove();
            }
        }
    }
}

function create_w1(){
    // app.activeDocument.layers[app.activeDocument.layers.length-1].visible=false
    app.activeDocument.activeLayer = app.activeDocument.layers[0]
    var idMrgV = charIDToTypeID("MrgV");
    executeAction(idMrgV, undefined, DialogModes.NO)
    // app.activeDocument.layers[app.activeDocument.layers.length-1].visible=true

    //make layer 0 selected
    app.activeDocument.activeLayer = app.activeDocument.layers[0]
    ctrlLayerSelect();
    var newSpotChannel = app.activeDocument.channels.add();
        newSpotChannel.kind = ChannelType.SPOTCOLOR
        newSpotChannel.color = red
        newSpotChannel.opacity = 100
        newSpotChannel.name = "W1";
    app.activeDocument.selection.store(newSpotChannel);
}
function merge_visible(){
    app.activeDocument.activeLayer = app.activeDocument.layers[0]
    var idMrgV = charIDToTypeID("MrgV");
    executeAction(idMrgV, undefined, DialogModes.NO)
}


function read_csv(csv_path){
    file = File(csv_path)

    file.open('r')

    content = file.read()

    content_arr = content.split('\n')

    rows = []

    for (i =1; i < content_arr.length; i++){
        if(content_arr[i].length>1){
            dump = content_arr[i].split(';')
            quantity = Number(dump[dump.length-1].replace(/"/g,'')[0])

            for(q = 0; q < quantity; q++){
                rows.push(dump)
            }
        }
    }
    data =[]
    for(i = 0; i < rows.length; i++){
        condition = rows[i][0]
        if(condition.search("Farbe 1")!=1) {continue};
        content = rows[i][5]
        letter_color = rows[i][2]
        letter = rows[i][3]
        box_text = ""
        box_condition = rows[i][7]
        if(box_condition == "Geschenkbox inkl. Namensgravur"){

            box_text = rows[i][9]
        }


        phrase = content.match(/^[^\(]+(?= \()/)
        if (phrase==null||phrase.length==0){
            phrase=''
        }
        else{
            phrase = phrase[0].replace(/\s+$/g,"")
        }
        dump  = content.match(/\([^()]+\)/g)[0]
        font = dump.replace('(','').replace(')','')
        if (phrase != ''){
            data.push([letter_color,letter,phrase,font,box_text])
        }
    }
    return data
}

function sortArrayByTextLengthDesc(myarray) {
    return myarray.sort(function(a, b) {
        return b[0].length - a[0].length;
    });
}


function placeEmbeded(filePath) {


    var actDesc = new ActionDescriptor();


    actDesc.putPath(charIDToTypeID('null'), filePath);


    actDesc.putEnumerated(charIDToTypeID('FTcs'), charIDToTypeID('QCSt'), charIDToTypeID('Qcsa'));


    executeAction(charIDToTypeID('Plc '), actDesc, DialogModes.NO);

 

}
function resizeImage(myLayer,t45){



    w = myLayer.bounds[2]-myLayer.bounds[0]
    h = myLayer.bounds[3]-myLayer.bounds[1]
    // Specify the width of the placeholder (frame)
    if (t45){

        var placeholderWidth = 624; // Replace with the desired width

        var placeholderHeight = 521;
    }
    else{

        var placeholderWidth = 1122; // Replace with the desired width

        var placeholderHeight = 1021;

    }


    // Calculate the scale factor
    if( w>h){

        var scaleFactor = placeholderWidth / (myLayer.bounds[2]-myLayer.bounds[0]); //Fix width
    }
    else{

        var scaleFactor = placeholderHeight / (myLayer.bounds[3]-myLayer.bounds[1]); //Fix Height
    }

    // Resize the layer
    myLayer.resize(scaleFactor * 100, scaleFactor * 100);

    // Refresh the document to see the changes
    // doc.refresh();
    // alert(app.activeDocument.activeLayer.bounds[3] - app.activeDocument.activeLayer.bounds[1])
}

function resizeTextToMaxWidth(layer) {
    var max = parseInt(layer.textItem.contents.length);
    
    // Check if text exceeds maximum width

    if (max > 17) {
        time_reduce = max-17

        // Reduce font size
        // for(u=0; u <time_reduce; u ++){

            layer.textItem.size = layer.textItem.size - time_reduce;
        // }

      
    }

}
function formatProduct3_text(myLayer){
    myText = myLayer.contents
    dumArr = myText.split('[linebreak]')
    myLayer.contents = myText.replace(/\[linebreak\]/g,'\r')
    if(dumArr.length == 2){
        maxChac = Math.max(dumArr[0].length,dumArr[1].length)
        threshold = 6
        myLayer.size = 36
        myLayer.leading = 30
        scale = maxChac-threshold
        myLayer.baselineShift =0
        if(scale>0){

            myLayer.size = myLayer.size - scale*2
            myLayer.leading = myLayer.size
            myLayer.baselineShift = - scale*2
        }
    }
    if(dumArr.length == 3){
        maxChac = Math.max(dumArr[0].length,dumArr[1].length,dumArr[2].length)
        threshold = 16
        myLayer.size = 24
        myLayer.leading = 20
        myLayer.baselineShift = -2
        scale = maxChac-threshold

        if(scale>0){

            myLayer.size = myLayer.size - scale*2
            myLayer.leading = myLayer.size
            myLayer.baselineShift = - scale*2
        }

    }
    else{
        maxChac = myText.length
        threshold = 5
        myLayer.size = 40
        myLayer.leading = 30
        scale = maxChac-threshold
        myLayer.baselineShift =-16
        if(scale>0){

            myLayer.size = myLayer.size - scale*2.3
            myLayer.leading = myLayer.size
            myLayer.baselineShift = myLayer.baselineShift - scale/2
        }
    }
}

// does the same thing a ctrol-clicking a layer;
function ctrlLayerSelect() {
    var id1 = charIDToTypeID( "setd" );
    var desc1 = new ActionDescriptor();
    var id2 = charIDToTypeID( "null" );
    var ref1 = new ActionReference();
    var id3 = charIDToTypeID( "Chnl" );
    var id4 = charIDToTypeID( "fsel" );
    ref1.putProperty( id3, id4 );
    desc1.putReference( id2, ref1 );
    var id5 = charIDToTypeID( "T   " );
    var ref2 = new ActionReference();
    var id6 = charIDToTypeID( "Chnl" );
    var id7 = charIDToTypeID( "Chnl" );
    var id8 = charIDToTypeID( "Trsp" );
    ref2.putEnumerated( id6, id7, id8 );
    desc1.putReference( id5, ref2 );
    executeAction( id1, desc1, DialogModes.NO );
}