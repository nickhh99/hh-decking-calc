(function(){
  "use strict";

  // ---------- productdata (zelfde als de rekentool) ----------
  var SPACING_MM = 6;
  var WASTE = 1.03;
  var MAAT = {
    100: { boardLenMm: 1860 },
    140: { boardLenMm: 1860 },
    200: { boardLenMm: 2200 }
  };
  var VISGRAAT = { widthMm: 140, boardLenMm: 700 };

  // Prijzen komen NIET hardcoded hier te staan: ze worden server-side live uit
  // WooCommerce gelezen (zie hh_bc_build_price_map() in hh-bamboe-configurator.php,
  // o.b.v. de product/variatie-ID's in includes/config.php) en via wp_localize_script
  // als window.HHBC.prices aangeleverd. Zo werkt een prijswijziging in de shop meteen
  // door, zonder dat hier iets aangepast hoeft te worden.
  var PRICES = (window.HHBC && window.HHBC.prices) || {};

  // Geeft het getal terug, of null als de prijs niet kon worden opgehaald (ontbrekend
  // product, WooCommerce niet actief, etc.) — nooit een verzonnen placeholderwaarde.
  function priceOf(v){ return (typeof v === 'number' && !isNaN(v)) ? v : null; }

  // Onderconstructie — zelfde rekenregels als Calculator::calc_regels() / calc_piketpalen() /
  // calc_granulaatpads() / calc_clips() / calc_slotbouten() / calc_olie() in
  // hh-decking-calc-v2/includes/class-calculator.php, voor bamboe (regelafstand 37,5cm,
  // regel = Bangkirai 40x60 3900mm ongeacht onderconstructie).
  var ACC_SPACING_M = 0.375;
  var REGEL_BEAM_LEN_M = 3.90;
  var REGEL_WASTE = 1.01;

  // Berekent de accessoires + hun prijs. `complete` geeft aan of ALLE benodigde
  // eenheidsprijzen beschikbaar waren — zo niet, dan toont de UI een waarschuwing
  // i.p.v. een te lage/foutieve richtprijs op basis van stilzwijgend weggelaten posten.
  function calcAccessories(lenM, widM, surfaceM2, plankQty, plankRows, pattern, poles, poleSize){
    var rowCount = Math.ceil(lenM/ACC_SPACING_M) + 1; // regel-/palenrijen (onafhankelijk van legrichting planken)
    var complete = true;
    function take(v){ var p = priceOf(v); if (p===null){ complete=false; return 0; } return p; }

    var regelQty = Math.ceil((rowCount*widM*REGEL_WASTE) / REGEL_BEAM_LEN_M);
    var regelPrice = regelQty * take(PRICES.regel);

    var palenQty = 0, padsQty = 0, palenPrice = 0, boutenQty = 0, boutenPrice = 0;
    if (poles === 'with'){
      palenQty = rowCount * (Math.ceil(widM/1.0) + 1);
      var paalPrice = poleSize==='50x50' ? take(PRICES.paal && PRICES.paal['50x50']) : take(PRICES.paal && PRICES.paal['40x40']);
      palenPrice = palenQty * paalPrice;
      boutenQty = Math.ceil(palenQty/25);
      var boutenDoosPrice = poleSize==='50x50' ? take(PRICES.slotbouten && PRICES.slotbouten['50x50']) : take(PRICES.slotbouten && PRICES.slotbouten['40x40']);
      boutenPrice = boutenQty * boutenDoosPrice;
    } else {
      padsQty = rowCount * (Math.ceil(widM/1.0) + 1);
      palenPrice = padsQty * take(PRICES.granulaatpad);
    }

    var tussenClipsTotal = pattern==='visgraat' ? plankQty*4 : plankRows*rowCount;
    var tussenDozen = Math.ceil(tussenClipsTotal/100);
    var clipsPrice = tussenDozen*take(PRICES.tussenclips);
    var startDozen = 0;
    if (pattern !== 'visgraat'){ startDozen = Math.ceil((rowCount*2)/25); clipsPrice += startDozen*take(PRICES.startclips); }

    var smallOlieNeeded = Math.ceil(surfaceM2/15);
    var largeOlie = Math.floor(smallOlieNeeded/3), smallOlie = smallOlieNeeded%3;
    var oliePrice = largeOlie*take(PRICES.olie && PRICES.olie.large) + smallOlie*take(PRICES.olie && PRICES.olie.small);

    return {
      rowCount: rowCount,
      regelQty: regelQty, regelPrice: regelPrice,
      poles: poles, palenQty: palenQty, padsQty: padsQty, palenPrice: palenPrice,
      boutenQty: boutenQty, boutenPrice: boutenPrice,
      tussenDozen: tussenDozen, startDozen: startDozen, clipsPrice: clipsPrice,
      largeOlie: largeOlie, smallOlie: smallOlie, oliePrice: oliePrice,
      total: regelPrice + palenPrice + boutenPrice + clipsPrice + oliePrice,
      complete: complete
    };
  }

  var COLORS = {
    espresso: { dark:[86,56,34], mid:[112,76,48], light:[138,100,68] },
    ebony:    { dark:[42,35,29], mid:[60,50,42], light:[80,68,58] }
  };

  function hash(n){ var x = Math.sin(n*12.9898+78.233)*43758.5453; return x - Math.floor(x); }
  function mix(a,b,t){ return [a[0]+(b[0]-a[0])*t, a[1]+(b[1]-a[1])*t, a[2]+(b[2]-a[2])*t]; }
  function rgb(c){ return 'rgb('+Math.round(c[0])+','+Math.round(c[1])+','+Math.round(c[2])+')'; }
  function plankColor(colorKey, seed){
    var c = COLORS[colorKey];
    var t = hash(seed);
    return mix(c.dark, c.light, 0.3 + t*0.4);
  }

  // ---------- rekenlogica ----------
  // "Recht": klassieke per-rij berekening — hoeveel planken passen er in 1 rij (afgerond
  // naar boven), keer het aantal rijen. Dat is hoe een legger het ook telt, en nauwkeuriger
  // dan simpelweg totale loopmeters delen (dat telt de afrondingsverliezen per rij niet mee).
  function calcRecht(lenM, widM, maatMm, richting){
    var rowPitchM = (maatMm + SPACING_MM)/1000;
    var boardLenM = MAAT[maatMm].boardLenMm/1000;
    var rowRunM, rowCount;
    if (richting === 'lengte'){ rowRunM = lenM; rowCount = Math.ceil(widM/rowPitchM); }
    else { rowRunM = widM; rowCount = Math.ceil(lenM/rowPitchM); }
    var boardsPerRow = Math.ceil(rowRunM/boardLenM);
    var boards = Math.ceil(boardsPerRow*rowCount*WASTE);
    return { boards: boards, rowCount: rowCount, boardsPerRow: boardsPerRow, rowPitchM: rowPitchM, boardLenM: boardLenM };
  }

  // Visgraat: oppervlakte-gebaseerd, net als in de rekentool — de richting bepaalt alleen
  // hoe het patroon ligt, niet het aantal planken (dat is bij visgraat altijd gelijk).
  function calcVisgraat(lenM, widM){
    var surface = lenM*widM;
    var plankM2 = (VISGRAAT.widthMm/1000)*(VISGRAAT.boardLenMm/1000);
    var wasteMult = surface < 15 ? 1.05 : 1.03;
    var boards = Math.ceil((surface/plankM2)*wasteMult);
    return { boards: boards, surface: surface };
  }

  // ---------- tekenen (plat patroon) ----------
  function drawPlank(ctx,x,y,w,h,colorKey,seed){
    if (w<=0||h<=0) return;
    var base = plankColor(colorKey, seed);
    ctx.fillStyle = rgb(base);
    ctx.fillRect(x,y,w,h);
    ctx.strokeStyle = 'rgba(0,0,0,0.12)';
    ctx.lineWidth = Math.max(0.5, Math.min(w,h)*0.03);
    var ly = y + h*(0.3+0.4*hash(seed+2));
    ctx.beginPath();
    if (w>=h){ ctx.moveTo(x+w*0.04, ly); ctx.lineTo(x+w*0.96, ly); }
    else { var lx = x + w*(0.3+0.4*hash(seed+2)); ctx.moveTo(lx, y+h*0.04); ctx.lineTo(lx, y+h*0.96); }
    ctx.stroke();
    ctx.strokeStyle = 'rgba(0,0,0,0.22)';
    ctx.lineWidth = 1;
    ctx.strokeRect(x+0.5,y+0.5,Math.max(0,w-1),Math.max(0,h-1));
  }

  // Tekent een plank met de ECHTE productfoto (zie initConfigurator → getPlankImage, die
  // de WooCommerce-afbeelding van het gekozen product ophaalt). De foto wordt "cover"-
  // gecropt per plankcel en om en om gespiegeld, zodat één foto niet als een duidelijk
  // herhaald kopie-plak-patroon oogt — plus een lichte, pseudo-random helderheidsvariatie
  // per plank voor wat natuurlijke variatie, net als bij echte vlonderplanken.
  function drawPhotoPlank(ctx,x,y,w,h,img,seed){
    if (w<=0||h<=0) return;
    var iw = img.naturalWidth, ih = img.naturalHeight;
    if (!iw || !ih) return;
    var scale = Math.max(w/iw, h/ih);
    var sw = w/scale, sh = h/scale;
    var sx = (iw-sw)/2, sy = (ih-sh)/2;
    var flip = hash(seed) > 0.5;
    ctx.save();
    if (flip){
      ctx.translate(x+w, y);
      ctx.scale(-1,1);
      ctx.drawImage(img, sx, sy, sw, sh, 0, 0, w, h);
    } else {
      ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
    }
    ctx.restore();
    var shade = 0.82 + hash(seed+5)*0.36;
    if (shade < 1){ ctx.fillStyle = 'rgba(0,0,0,'+((1-shade)*0.35).toFixed(3)+')'; ctx.fillRect(x,y,w,h); }
    else if (shade > 1){ ctx.fillStyle = 'rgba(255,255,255,'+((shade-1)*0.25).toFixed(3)+')'; ctx.fillRect(x,y,w,h); }
    ctx.strokeStyle = 'rgba(0,0,0,0.25)';
    ctx.lineWidth = 1;
    ctx.strokeRect(x+0.5,y+0.5,Math.max(0,w-1),Math.max(0,h-1));
  }

  // Rechte planken: rijen lopen in de gekozen richting, met verspringende (wildverband) naden.
  // `photoImg` (optioneel): echte productfoto — zodra geladen wordt die gebruikt i.p.v. de
  // procedureel getekende placeholder-plank.
  function drawStraightField(ctx,x0,y0,w,h,pxPerM,maatMm,richting,colorKey,photoImg){
    var pw = (maatMm/1000)*pxPerM;
    var pl = (MAAT[maatMm].boardLenMm/1000)*pxPerM;
    var vertical = (richting === 'breedte');
    ctx.fillStyle = rgb(plankColor(colorKey,0));
    ctx.fillRect(x0,y0,w,h);

    var usePhoto = !!(photoImg && photoImg.complete && photoImg.naturalWidth);
    function plank(x,y,ww,hh,seed){
      if (usePhoto) drawPhotoPlank(ctx,x,y,ww,hh,photoImg,seed);
      else drawPlank(ctx,x,y,ww,hh,colorKey,seed);
    }

    var offsets = [0,0.5,0.22,0.72];
    var seed = 0;
    if (!vertical){
      var rows = Math.ceil(h/pw)+1;
      for (var r=0;r<rows;r++){
        var y = y0 + r*pw;
        var off = offsets[r%offsets.length]*pl;
        var x = x0 - pl + off;
        while (x < x0+w){ plank(x,y,pl,Math.min(pw*0.94,y0+h-y),seed++); x += pl; }
      }
    } else {
      var cols = Math.ceil(w/pw)+1;
      for (var c=0;c<cols;c++){
        var x2 = x0 + c*pw;
        var off2 = offsets[c%offsets.length]*pl;
        var y2 = y0 - pl + off2;
        while (y2 < y0+h){ plank(x2,y2,Math.min(pw*0.94,x0+w-x2),pl,seed++); y2 += pl; }
      }
    }
  }

  // Visgraat: haaks op elkaar staande planken in een trapsgewijs patroon, over de hele
  // diagonaal getekend en daarna op de terrasvorm geclipt. "Breedte"-richting spiegelt
  // het patroon zodat de v-vorm de andere kant op wijst.
  function drawHerringboneField(ctx,x0,y0,w,h,pxPerM,richting,colorKey,photoImg){
    ctx.fillStyle = rgb(plankColor(colorKey,0));
    ctx.fillRect(x0,y0,w,h);
    ctx.save();
    var cx = x0+w/2, cy = y0+h/2;
    var angle = (richting==='breedte' ? -1 : 1) * Math.PI/4;
    ctx.translate(cx,cy); ctx.rotate(angle); ctx.translate(-cx,-cy);

    var pw = Math.max(5, Math.min(w,h)*0.085);
    var pl = pw*3;
    var diag = Math.sqrt(w*w+h*h) + pl*2;
    var startX = cx-diag/2, startY = cy-diag/2, endX = cx+diag/2, endY = cy+diag/2;
    var usePhoto = !!(photoImg && photoImg.complete && photoImg.naturalWidth);
    function plank(x,y,ww,hh,seed){
      if (usePhoto) drawPhotoPlank(ctx,x,y,ww,hh,photoImg,seed);
      else drawPlank(ctx,x,y,ww,hh,colorKey,seed);
    }
    var row=0, seed=0;
    for (var y=startY; y<endY; y+=pw){
      var horizontal = (row%2===0);
      var step = horizontal ? pl : pw;
      var x = startX;
      while (x<endX){
        if (horizontal) plank(x,y,pl,pw*0.92,seed++);
        else plank(x,y,pw*0.92,pl,seed++);
        x += step;
      }
      row++;
    }
    ctx.restore();
  }

  // ---------- tekenen (schuine/verhoogde weergave) ----------
  // Het platte patroon (hierboven, ongewijzigd t.o.v. de rekenlogica) wordt op een los
  // canvas getekend en vervolgens in dunne horizontale stroken op het zichtbare canvas
  // geplakt, elk met een iets kleinere breedte naar "achteren" toe. Zo ontstaat een
  // trapezium — alsof je schuin van boven op het terras kijkt — zonder dat de
  // plankentekenlogica zelf hoeft te veranderen.
  var flatCanvas = document.createElement('canvas');
  var TOP_SCALE = 0.62;     // breedte van de verste rand t.o.v. de dichtstbijzijnde rand
  var VERT_COMPRESS = 0.90; // hoeveel van de beschikbare hoogte het trapezium gebruikt

  function buildFlatPattern(state, flatW, flatH, getImage){
    flatCanvas.width = flatW;
    flatCanvas.height = flatH;
    var fctx = flatCanvas.getContext('2d');
    var pxPerM = flatW/state.lengteM;
    var img = getImage ? getImage(state) : null;
    if (state.pattern === 'visgraat') drawHerringboneField(fctx,0,0,flatW,flatH,pxPerM,state.richting,state.color,img);
    else drawStraightField(fctx,0,0,flatW,flatH,pxPerM,state.maatMm,state.richting,state.color,img);
    return flatCanvas;
  }

  function trapCorners(boxX,boxY,boxW,boxH){
    function proj(t){
      var rowW = boxW*(1-(1-TOP_SCALE)*t);
      var x0 = boxX + (boxW-rowW)/2;
      var y = boxY + boxH - t*boxH*VERT_COMPRESS;
      return { x0:x0, x1:x0+rowW, y:y };
    }
    var near = proj(0), far = proj(1);
    return [[near.x0,near.y],[near.x1,near.y],[far.x1,far.y],[far.x0,far.y]];
  }

  function pathFromCorners(ctx,c){
    ctx.beginPath();
    ctx.moveTo(c[0][0],c[0][1]);
    ctx.lineTo(c[1][0],c[1][1]);
    ctx.lineTo(c[2][0],c[2][1]);
    ctx.lineTo(c[3][0],c[3][1]);
    ctx.closePath();
  }

  function render(canvas, state, getImage){
    var ctx = canvas.getContext('2d');
    var dpr = Math.min(window.devicePixelRatio||1,2);
    var cssW = canvas.clientWidth, cssH = canvas.clientHeight;
    if (cssW<=0||cssH<=0) return;
    canvas.width = cssW*dpr; canvas.height = cssH*dpr;
    ctx.setTransform(dpr,0,0,dpr,0,0);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';

    var bg = ctx.createLinearGradient(0,0,0,cssH);
    bg.addColorStop(0,'#f7f8f6');
    bg.addColorStop(1,'#e8ebe8');
    ctx.fillStyle = bg;
    ctx.fillRect(0,0,cssW,cssH);

    var margin = state.compact ? 4 : 12;
    var availW = cssW-margin*2, availH = cssH-margin*2;
    if (availW<=0||availH<=0) return;
    var ratio = state.lengteM/state.breedteM;
    var boxW,boxH;
    if (availW/availH>ratio){ boxH=availH; boxW=boxH*ratio; } else { boxW=availW; boxH=boxW/ratio; }
    var boxX=(cssW-boxW)/2, boxY=(cssH-boxH)/2;

    var corners = trapCorners(boxX,boxY,boxW,boxH);

    // grondschaduw, zodat het terras "op de grond" lijkt te staan
    ctx.save();
    ctx.translate(0,5);
    ctx.filter = 'blur(5px)';
    ctx.fillStyle = 'rgba(0,0,0,0.14)';
    pathFromCorners(ctx,corners);
    ctx.fill();
    ctx.restore();

    // plat patroon opbouwen en schuin op het canvas plakken
    var flatW = state.compact ? 220 : 640;
    var flatH = Math.max(1, Math.round(flatW * (state.breedteM/state.lengteM)));
    var flat = buildFlatPattern(state, flatW, flatH, getImage);

    ctx.save();
    pathFromCorners(ctx,corners);
    ctx.clip();
    var strips = state.compact ? 24 : 60;
    for (var i=0;i<strips;i++){
      var t0=i/strips, t1=(i+1)/strips, tMid=(t0+t1)/2;
      var rowW = boxW*(1-(1-TOP_SCALE)*tMid);
      var destX = boxX + (boxW-rowW)/2;
      var sy0 = boxY+boxH - t0*boxH*VERT_COMPRESS;
      var sy1 = boxY+boxH - t1*boxH*VERT_COMPRESS;
      var destY = Math.min(sy0,sy1);
      var destH = Math.max(1, Math.abs(sy1-sy0)+1);
      var srcY = t0*flatH;
      var srcH = Math.max(1,(t1-t0)*flatH);
      ctx.drawImage(flat, 0, srcY, flatW, srcH, destX, destY, Math.max(1,rowW), destH);
    }
    ctx.restore();

    // afwerkrand (boeideel), geeft het terras een duidelijke, nette rand
    ctx.strokeStyle = 'rgba(35,26,18,0.55)';
    ctx.lineWidth = state.compact ? 1.25 : 2.25;
    pathFromCorners(ctx,corners);
    ctx.stroke();
  }

  // ---------- init per widget-instantie ----------
  // Ondersteunt meerdere configurators op 1 pagina: alles wordt gescoped op de root
  // (.hh-bc-app) i.p.v. globale document.getElementById, dus geen id-botsing mogelijk.
  function initConfigurator(root){
    var state = { lengteM:5.0, breedteM:3.0, pattern:'recht', richting:'lengte', maatMm:140, color:'espresso', poles:'none', poleSize:'40x40' };
    // Kortingscode + percentage komen uit de shortcode-attributen (promo_code/promo_pct),
    // zodat de code op de pagina (data-attribuut, server-side) en de check hier altijd gelijk lopen.
    var PROMO_CODE = (root.getAttribute('data-hhbc-promo-code') || 'BAMBOE15').toUpperCase();
    var PROMO_PCT = parseFloat(root.getAttribute('data-hhbc-promo-pct')) || 15;
    var discountPct=0, discountApplied=false;
    // Laatst getoonde berekening — de "In winkelmand"-knop bouwt de regels hieruit op,
    // zodat de mand exact overeenkomt met wat de klant net zag (geen herberekening-race).
    var lastCalc=null, lastAcc=null, lastPricesComplete=false;

    function $(sel){ return root.querySelector(sel); }
    function $all(sel){ return root.querySelectorAll(sel); }

    var stageCanvas = $('#hhbc-stage');
    var inLengte = $('#hhbc-inLengte');
    var inBreedte = $('#hhbc-inBreedte');

    // Echte productfoto's (WooCommerce featured image per product-ID), live meegegeven
    // door hh_bc_build_image_map() in hh-bamboe-configurator.php — zie window.HHBC.images.
    // Geen URL bekend of nog niet geladen? Dan valt de tekenlogica terug op de procedurele
    // placeholder-planken (zie drawStraightField/drawHerringboneField's `usePhoto`-check).
    var IMAGES = (window.HHBC && window.HHBC.images) || {};
    var imageCache = {};
    function getPlankImage(st){
      var url = st.pattern === 'visgraat'
        ? (IMAGES.visgraat && IMAGES.visgraat[st.color])
        : (IMAGES.planks && IMAGES.planks[st.maatMm] && IMAGES.planks[st.maatMm][st.color]);
      if (!url) return null;
      var img = imageCache[url];
      if (!img){
        img = new Image();
        img.onload = function(){ updateAll(); };
        img.src = url;
        imageCache[url] = img;
      }
      return img;
    }

    function fmtEUR(n){ return '€ '+n.toLocaleString('nl-NL',{minimumFractionDigits:0,maximumFractionDigits:0}); }
    function fmtM2(n){ return n.toLocaleString('nl-NL',{minimumFractionDigits:1,maximumFractionDigits:1})+' m²'; }

    function syncMaatAvailability(){
      var locked = state.pattern==='visgraat';
      $all('#hhbc-maatRow .hh-bc-opt[data-hhbc-mm]').forEach(function(opt){
        var mm = opt.getAttribute('data-hhbc-mm');
        var input = opt.querySelector('input');
        input.disabled = locked && mm!=='140';
      });
      if (locked){ state.maatMm = 140; var r=$('#hhbc-maatRow .hh-bc-opt[data-hhbc-mm="140"] input'); if(r) r.checked=true; }
    }

    // Ebony bestaat in de catalogus alleen bij de 140mm vlonderplank (zie
    // includes/config.php, PRODUCT_IDS['planks']) — bij 100/200mm recht, of als die maat
    // nog niet gekozen is, is alleen espresso leverbaar. Visgraat heeft beide kleuren.
    function syncColorAvailability(){
      var onlyEspresso = state.pattern !== 'visgraat' && state.maatMm !== 140;
      var ebonyInput = $('#hhbc-colorRow input[value="ebony"]');
      if (ebonyInput) ebonyInput.disabled = onlyEspresso;
      if (onlyEspresso && state.color === 'ebony'){
        state.color = 'espresso';
        var espressoInput = $('#hhbc-colorRow input[value="espresso"]');
        if (espressoInput) espressoInput.checked = true;
      }
    }

    function currentCalc(){
      if (state.pattern==='visgraat') return calcVisgraat(state.lengteM, state.breedteM);
      return calcRecht(state.lengteM, state.breedteM, state.maatMm, state.richting);
    }

    function updateAll(){
      syncMaatAvailability();
      syncColorAvailability();
      $('#hhbc-poleSizeWrapper').hidden = (state.poles !== 'with');

      var calc = currentCalc();
      var pricePerBoard = state.pattern==='visgraat'
        ? priceOf(PRICES.visgraat && PRICES.visgraat[state.color])
        : priceOf(PRICES.planks && PRICES.planks[state.maatMm] && PRICES.planks[state.maatMm][state.color]);
      var boardsPriceOk = pricePerBoard !== null;
      var boardsPrice = boardsPriceOk ? calc.boards*pricePerBoard : 0;
      var surfaceM2 = state.lengteM*state.breedteM;
      var acc = calcAccessories(state.lengteM, state.breedteM, surfaceM2, calc.boards, calc.rowCount||0, state.pattern, state.poles, state.poleSize);
      var pricesComplete = boardsPriceOk && acc.complete;

      var subtotal = boardsPrice + acc.total;
      var discount = discountApplied ? subtotal*(discountPct/100) : 0;
      var total = Math.round(subtotal-discount);

      lastCalc = calc; lastAcc = acc; lastPricesComplete = pricesComplete;

      var warningEl = $('#hhbc-priceWarning');
      var cartBtnEl = $('#hhbc-cartBtn');
      if (!pricesComplete){
        warningEl.hidden = false;
        cartBtnEl.disabled = true;
        $('#hhbc-prTotal').textContent = '—';
        $('#hhbc-prPerM2').textContent = '—';
      } else {
        warningEl.hidden = true;
        cartBtnEl.disabled = false;
        $('#hhbc-prTotal').textContent = fmtEUR(total);
        $('#hhbc-prPerM2').textContent = fmtEUR(Math.round(total/surfaceM2));
      }
      $('#hhbc-odSurface').textContent = fmtM2(surfaceM2);
      $('#hhbc-odPattern').textContent = (state.pattern==='visgraat'?'Visgraat':'Recht') + ' · in de ' + state.richting;
      $('#hhbc-odMaat').textContent = (state.pattern==='visgraat'?'140 mm':state.maatMm+' mm') + ' · ' + (state.color==='espresso'?'Espresso':'Ebony');

      $('#hhbc-liBoardsQty').textContent = calc.boards;
      $('#hhbc-liBoards').textContent = fmtEUR(Math.round(boardsPrice));
      $('#hhbc-liRegelsQty').textContent = acc.regelQty;
      $('#hhbc-liRegels').textContent = fmtEUR(Math.round(acc.regelPrice));
      $('#hhbc-liPalenLabel').textContent = state.poles==='with' ? ('Piketpalen ('+acc.palenQty+'×)') : ('Granulaatpads ('+acc.padsQty+'×)');
      $('#hhbc-liPalen').textContent = fmtEUR(Math.round(acc.palenPrice));
      var boutenRow = $('#hhbc-liBoutenRow');
      if (acc.boutenQty>0){ boutenRow.hidden=false; $('#hhbc-liBouten').textContent = fmtEUR(Math.round(acc.boutenPrice)); }
      else { boutenRow.hidden=true; }
      $('#hhbc-liClips').textContent = fmtEUR(Math.round(acc.clipsPrice));
      $('#hhbc-liOlie').textContent = fmtEUR(Math.round(acc.oliePrice));

      var discRow = $('#hhbc-srDiscountRow');
      if (discountApplied){ discRow.hidden=false; $('#hhbc-srDiscountPct').textContent=discountPct; $('#hhbc-srDiscount').textContent='− '+fmtEUR(Math.round(discount)); }
      else { discRow.hidden=true; }

      requestAnimationFrame(function(){ render(stageCanvas, state, getPlankImage); });
      renderPresetThumbs();
      renderSwatches();
    }

    $('#hhbc-patternRow').addEventListener('change', function(e){ if(e.target.name==='hhbc-pattern'){ state.pattern=e.target.value; updateAll(); } });
    $('#hhbc-richtingRow').addEventListener('change', function(e){ if(e.target.name==='hhbc-richting'){ state.richting=e.target.value; updateAll(); } });
    $('#hhbc-maatRow').addEventListener('change', function(e){ if(e.target.name==='hhbc-maat'){ state.maatMm=parseInt(e.target.value,10); updateAll(); } });
    $('#hhbc-colorRow').addEventListener('change', function(e){ if(e.target.name==='hhbc-color'){ state.color=e.target.value; updateAll(); } });
    $('#hhbc-polesRow').addEventListener('change', function(e){ if(e.target.name==='hhbc-poles'){ state.poles=e.target.value; updateAll(); } });
    $('#hhbc-poleSizeRow').addEventListener('change', function(e){ if(e.target.name==='hhbc-poleSize'){ state.poleSize=e.target.value; updateAll(); } });

    function clampNum(v,min,max){ v=parseFloat(v); if(isNaN(v)) return min; return Math.max(min,Math.min(max,v)); }
    inLengte.addEventListener('input', function(){ state.lengteM=clampNum(inLengte.value,1,20); updateAll(); });
    inBreedte.addEventListener('input', function(){ state.breedteM=clampNum(inBreedte.value,1,20); updateAll(); });
    $('#hhbc-lenMinus').addEventListener('click', function(){ state.lengteM=clampNum(state.lengteM-0.5,1,20); inLengte.value=state.lengteM; updateAll(); });
    $('#hhbc-lenPlus').addEventListener('click', function(){ state.lengteM=clampNum(state.lengteM+0.5,1,20); inLengte.value=state.lengteM; updateAll(); });
    $('#hhbc-breMinus').addEventListener('click', function(){ state.breedteM=clampNum(state.breedteM-0.5,1,20); inBreedte.value=state.breedteM; updateAll(); });
    $('#hhbc-brePlus').addEventListener('click', function(){ state.breedteM=clampNum(state.breedteM+0.5,1,20); inBreedte.value=state.breedteM; updateAll(); });

    function renderSwatches(){
      $all('canvas[data-hhbc-swatch]').forEach(function(cv){
        var key = cv.getAttribute('data-hhbc-swatch');
        var dpr = Math.min(window.devicePixelRatio||1,2);
        var w = cv.clientWidth||26, h = cv.clientHeight||19;
        if (!w||!h) return;
        cv.width=w*dpr; cv.height=h*dpr;
        var ctx = cv.getContext('2d'); ctx.setTransform(dpr,0,0,dpr,0,0);
        var swatchImg = getPlankImage({ pattern:'recht', maatMm:140, color:key });
        drawStraightField(ctx,0,0,w,h,1,140,'lengte',key,swatchImg);
      });
    }

    // ---------- presets ----------
    var PRESETS = [
      { name:'Visgraat', pattern:'visgraat', richting:'lengte', maatMm:140, color:'espresso' },
      { name:'200 Ebony', pattern:'recht', richting:'lengte', maatMm:200, color:'ebony' },
      { name:'140 Espresso', pattern:'recht', richting:'breedte', maatMm:140, color:'espresso' },
      { name:'100 Ebony', pattern:'recht', richting:'lengte', maatMm:100, color:'ebony' }
    ];
    var presetRow = $('#hhbc-presetRow');
    PRESETS.forEach(function(p){
      var card = document.createElement('div');
      card.className='hh-bc-preset';
      card.innerHTML = '<canvas></canvas><div class="hh-bc-p-name"></div>';
      card.querySelector('.hh-bc-p-name').textContent = p.name;
      card.addEventListener('click', function(){
        state.pattern=p.pattern; state.richting=p.richting; state.maatMm=p.maatMm; state.color=p.color;
        $('#hhbc-patternRow input[value="'+p.pattern+'"]').checked=true;
        $('#hhbc-richtingRow input[value="'+p.richting+'"]').checked=true;
        syncMaatAvailability();
        var maatInput = $('#hhbc-maatRow .hh-bc-opt[data-hhbc-mm="'+p.maatMm+'"] input');
        if (maatInput && !maatInput.disabled) maatInput.checked=true;
        $('#hhbc-colorRow input[value="'+p.color+'"]').checked=true;
        updateAll();
      });
      presetRow.appendChild(card);
    });
    function renderPresetThumbs(){
      var cards = presetRow.querySelectorAll('.hh-bc-preset');
      PRESETS.forEach(function(p,i){
        var cv = cards[i].querySelector('canvas');
        var dpr = Math.min(window.devicePixelRatio||1,2);
        var w=cv.clientWidth,h=cv.clientHeight;
        if(!w||!h) return;
        cv.width=w*dpr; cv.height=h*dpr;
        render(cv, { lengteM:4, breedteM:3, pattern:p.pattern, richting:p.richting, maatMm:p.maatMm, color:p.color, compact:true }, getPlankImage);
      });
    }

    // ---------- korting ----------
    $('#hhbc-applyDiscountBtn').addEventListener('click', function(){
      var code = $('#hhbc-inDiscount').value.trim().toUpperCase();
      var msg = $('#hhbc-discountMsg');
      if (code===PROMO_CODE){ discountApplied=true; discountPct=PROMO_PCT; msg.textContent='Code toegepast: '+PROMO_PCT+'% korting.'; msg.className='hh-bc-discount-msg hh-bc-ok'; }
      else if (code.length===0){ discountApplied=false; msg.textContent=''; msg.className='hh-bc-discount-msg'; }
      else { discountApplied=false; msg.textContent='Onbekende code.'; msg.className='hh-bc-discount-msg hh-bc-err'; }
      updateAll();
    });

    // ---------- winkelmand (echte WooCommerce add-to-cart) ----------
    var IDS = (window.HHBC && window.HHBC.ids) || null;
    var REST = (window.HHBC && window.HHBC.rest) || null;
    var NONCE = (window.HHBC && window.HHBC.nonce) || '';
    var I18N = (window.HHBC && window.HHBC.i18n) || {};

    // Bouwt de regels (product/variatie-ID + aantal) voor de huidige configuratie, met
    // dezelfde product-ID's als hh_bc_build_price_map() in hh-bamboe-configurator.php.
    function buildCartLines(){
      if (!IDS || !lastCalc || !lastAcc) return null;
      var lines = [];

      var boardId = state.pattern === 'visgraat'
        ? (IDS.visgraat && IDS.visgraat[state.color])
        : (IDS.planks && IDS.planks[state.maatMm] && IDS.planks[state.maatMm][state.color]);
      if (!boardId) return null;
      lines.push({ product_id: boardId, variation_id: 0, qty: lastCalc.boards });

      if (lastAcc.regelQty > 0){
        lines.push({ product_id: IDS.regel.product, variation_id: IDS.regel.variation, qty: lastAcc.regelQty });
      }

      if (state.poles === 'with'){
        var paal = IDS.piketpaal[state.poleSize];
        if (lastAcc.palenQty > 0 && paal){
          lines.push({ product_id: paal.product, variation_id: paal.variation, qty: lastAcc.palenQty });
        }
        var bouten = IDS.slotbouten[state.poleSize];
        if (lastAcc.boutenQty > 0 && bouten){
          lines.push({ product_id: bouten.product, variation_id: bouten.variation, qty: lastAcc.boutenQty });
        }
      } else if (lastAcc.padsQty > 0){
        lines.push({ product_id: IDS.granulaatpad, variation_id: 0, qty: lastAcc.padsQty });
      }

      if (lastAcc.tussenDozen > 0){
        lines.push({ product_id: IDS.tussenclips, variation_id: 0, qty: lastAcc.tussenDozen });
      }
      if (lastAcc.startDozen > 0){
        lines.push({ product_id: IDS.startclips, variation_id: 0, qty: lastAcc.startDozen });
      }
      if (lastAcc.largeOlie > 0){
        lines.push({ product_id: IDS.olie.large, variation_id: 0, qty: lastAcc.largeOlie });
      }
      if (lastAcc.smallOlie > 0){
        lines.push({ product_id: IDS.olie.small, variation_id: 0, qty: lastAcc.smallOlie });
      }

      return lines;
    }

    var cartMsgEl = $('#hhbc-cartMsg');
    function setCartMsg(text){
      if (!cartMsgEl) return;
      if (text){ cartMsgEl.textContent = text; cartMsgEl.hidden = false; }
      else { cartMsgEl.hidden = true; }
    }

    $('#hhbc-cartBtn').addEventListener('click', function(){
      var btn = this;
      if (btn.disabled || !lastPricesComplete) return;
      setCartMsg(null);

      var lines = buildCartLines();
      if (!lines || !lines.length || !REST){
        setCartMsg(I18N.cartError || 'Toevoegen aan winkelmand is niet gelukt.');
        return;
      }

      var originalText = 'In winkelmand';
      btn.disabled = true;
      btn.textContent = 'Bezig...';

      fetch(REST.base + '/add-to-cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        body: JSON.stringify({ lines: lines })
      })
        .then(function(res){ return res.json(); })
        .then(function(data){
          if (data && data.success){
            btn.textContent = '✓ Toegevoegd';
            btn.classList.add('hh-bc-added');
            setTimeout(function(){
              if (data.cart_url) window.location.href = data.cart_url;
            }, 500);
          } else {
            var msg = (data && data.out_of_stock && data.items && data.items.length)
              ? 'Niet op voorraad: ' + data.items.join(', ')
              : (I18N.cartError || 'Toevoegen aan winkelmand is niet gelukt.');
            setCartMsg(msg);
            btn.disabled = false;
            btn.textContent = originalText;
          }
        })
        .catch(function(){
          setCartMsg(I18N.cartError || 'Toevoegen aan winkelmand is niet gelukt.');
          btn.disabled = false;
          btn.textContent = originalText;
        });
    });

    // ---------- vorm-notitie (onthoudt dismiss per bezoeker, niet kritisch als opslag faalt) ----------
    var shapeNote = $('#hhbc-shapeNote');
    try {
      if (localStorage.getItem('hh_bc_shape_note_dismissed') === '1') shapeNote.hidden = true;
    } catch(e){}
    $('#hhbc-shapeNoteClose').addEventListener('click', function(){
      shapeNote.hidden = true;
      try { localStorage.setItem('hh_bc_shape_note_dismissed','1'); } catch(e){}
    });

    var ro = new ResizeObserver(function(){ updateAll(); });
    ro.observe(stageCanvas.parentElement);
    window.addEventListener('resize', updateAll);

    updateAll();
  }

  function boot(){
    document.querySelectorAll('.hh-bc-app').forEach(initConfigurator);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
