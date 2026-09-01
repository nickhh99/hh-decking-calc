document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("dc-form");
    const resultContainer = document.getElementById("dc-result-container");
    const resultBox = document.getElementById("dc-result");
    const navButtons = document.querySelector(".hh-dc-nav-buttons");

    // Knoppen
    const addToCartBtn = document.getElementById("dc-add-to-cart");
    const restartBtn = document.getElementById("dc-btn-restart");
    const prevBtn = document.getElementById("dc-btn-prev");
    const nextBtn = document.getElementById("dc-btn-next");
    const calcBtn = document.getElementById("dc-btn-calc");

    // Containers (Wrappers en Grids)
    const subtypeWrapper = document.getElementById("dc-subtype-wrapper");
    const subtypeContainer = document.getElementById("dc-subtype-container");
    
    const colorWrapper = document.getElementById("dc-color-wrapper");
    const colorContainer = document.getElementById("dc-color-container");
    const colorInputHidden = document.getElementById("dc-color-input");

    // Dikte (Height)
    const heightWrapper = document.getElementById("dc-height-wrapper");
    const heightContainer = document.getElementById("dc-height-container");
    const heightInputHidden = document.getElementById("dc-height-input");

    // Onderconstructie (Poles)
    const polesWrapper = document.getElementById("dc-poles-wrapper");
    const polesContainer = document.getElementById("dc-poles-container");
    
    // Paalmaat
    const poleSizeWrapper = document.getElementById("dc-pole-size-wrapper");
    const poleSizeContainer = document.getElementById("dc-pole-size-container");

    // Progress & Slides
    const slides = form.querySelectorAll(".hh-dc-slide");
    const stepDots = document.querySelectorAll(".step-dot");
    const progressFill = document.getElementById("dc-progress-fill");

    let currentStep = 1;
    const totalSteps = slides.length; 

    // --- ENTER TOETS NAVIGATIE ---
    form.addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
            if (e.target.tagName === "BUTTON") return;
            e.preventDefault(); 
            
            if (currentStep < totalSteps) {
                nextBtn.click();
            } else {
                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.dispatchEvent(new Event('submit', {cancelable: true}));
                }
            }
        }
    });

    // --- CONFIG DATA MET AFBEELDINGEN ---
    
    // 1. Subtypes (Uitvoeringen)
    const SUBTYPE_OPTIONS = {
        hout: [
            { value: "bangkirai", label: "Bangkirai", img: "https://www.haarlemsehouthandel.nl/wp-content/uploads/2021/05/Bangkirai-Dekdeel-21×145-mm-525x420.png", desc: "Duurzaam hardhout." },
            { value: "angelim", label: "Angelim Vermelho", img: "https://www.haarlemsehouthandel.nl/wp-content/uploads/2024/12/fsc100-angelim-vermelho-balk-geschaafd-p201-05-fas-ad-45x190mm-525x644.png", desc: "Zeer stabiel en sterk." },
            { value: "douglas", label: "Douglas", img: "https://www.haarlemsehouthandel.nl/wp-content/uploads/2021/05/Douglas-geschaafde-plank-28-195mm-525x420.png", desc: "Budgetvriendelijk." }
        ],
        bamboe: [
            { value: "plank", label: "Vlonderplank", img: "https://www.haarlemsehouthandel.nl/wp-content/uploads/2022/06/IMG_4435-525x700.jpg", desc: "Standaard plank." },
            { value: "tegel", label: "Vlondertegel", img: "https://www.haarlemsehouthandel.nl/wp-content/uploads/2025/06/6d9621ac-0d9b-4d59-a423-7ecbf5a1bcf2-525x416.jpg", desc: "Makkelijk te leggen." },
            { value: "visgraat", label: "Visgraat", img: "https://www.haarlemsehouthandel.nl/wp-content/uploads/2024/06/IMG_4431-525x700.jpg", desc: "Luxe patroon." }
        ]
    };

    // 2. Kleuren
    const COLOR_IMAGES = {
        'stone_grey': 'https://www.haarlemsehouthandel.nl/wp-content/uploads/2023/01/IMG_0189.jpg', 
        'ipe':        'https://www.haarlemsehouthandel.nl/wp-content/uploads/2023/01/IMG_0187.jpg',
        'teak':       'https://www.haarlemsehouthandel.nl/wp-content/uploads/2025/04/resized_to_match_reference.png',
        'ebony':      'https://www.haarlemsehouthandel.nl/wp-content/uploads/2023/01/IMG_0185.jpg',
        'espresso':   'https://www.haarlemsehouthandel.nl/wp-content/uploads/2024/06/IMG_4431-525x700.jpg'
    };

    // 3. Onderconstructie
    const POLE_OPTIONS = [
        { value: "none", label: "Balkon / Beton", desc: "Op tegeldagers (geen palen)." },
        { value: "with", label: "In de Tuin", desc: "Met piketpalen de grond in." }
    ];

    // 4. Paalmaat
    const POLE_SIZE_OPTIONS = [
        { value: "40x40", label: "40x40 mm", desc: "Standaard." },
        { value: "50x50", label: "50x50 mm", desc: "Extra robuust." }
    ];

    // Helper: Haal geselecteerde radio waarde op
    function getRadioValue(name) {
        const el = form.querySelector(`input[name="${name}"]:checked`);
        return el ? el.value : "";
    }

    // --- RENDER FUNCTIE (ondersteunt disabled + note) ---
    function renderCards(container, name, options, selectedValue = "") {
        if (!container) return; 
        container.innerHTML = "";
        
        options.forEach(opt => {
            const isChecked = (String(opt.value) === String(selectedValue));
            const isDisabled = !!opt.disabled;
            const imgHtml = opt.img ? `<div class="hh-dc-card-img" style="background-image: url('${opt.img}');"></div>` : '';
            const noteHtml = opt.note ? `<span class="hh-dc-card-note">${opt.note}</span>` : '';

            const html = `
                <label class="hh-dc-card-option${isDisabled ? ' disabled' : ''}">
                    <input type="radio" name="${name}" value="${opt.value}" ${isChecked && !isDisabled ? 'checked' : ''} ${isDisabled ? 'disabled' : ''}>
                    <div class="hh-dc-card-inner">
                        ${imgHtml}
                        <div class="hh-dc-card-content">
                            <span class="hh-dc-card-title">${opt.label}</span>
                            ${opt.desc ? `<span class="hh-dc-card-desc">${opt.desc}</span>` : ''}
                            ${noteHtml}
                        </div>
                    </div>
                </label>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });
    }

    // --- LOGICA ---
    function updateSubtypeOptions() {
        const type = getRadioValue("type");
        const currentSub = getRadioValue("subtype");

        if ((type === "hout" || type === "bamboe") && subtypeContainer) {
            const opts = SUBTYPE_OPTIONS[type] || [];
            renderCards(subtypeContainer, "subtype", opts, currentSub);
            subtypeWrapper.style.display = "block";
        } else {
            if(subtypeWrapper) subtypeWrapper.style.display = "none";
        }
        updateFields();
    }

    // Bamboe vlonderplanken zijn altijd 18mm dik en verschillen alleen in BREEDTE
    // (width_mm). Voor die combinatie gebruiken we width_mm als "maat"-dimensie
    // in plaats van thick_mm, anders is er geen manier om tussen de breedtes te kiezen.
    function isBamboePlank(type, subtype) {
        return type === 'bamboe' && subtype === 'plank';
    }

    function getValidOptions(type, subtype, currentHeight, currentColor) {
        const validHeights = new Set();
        const validColors = new Set();
        const sizeField = isBamboePlank(type, subtype) ? 'width_mm' : 'thick_mm';

        if (typeof HHDC2 !== 'undefined' && HHDC2.config && HHDC2.config.mappings) {
            Object.values(HHDC2.config.mappings).forEach((map) => {
              if (map.type !== type) return;
              const mapSubtype = map.subtype || "";
              if (mapSubtype !== subtype) return;

              let colorMatch = true;
              if (currentColor && map.color && map.color !== currentColor) colorMatch = false;
              if (colorMatch && map[sizeField] && map[sizeField] > 0) validHeights.add(map[sizeField]);

              let heightMatch = true;
              if (currentHeight && map[sizeField] && map[sizeField] != currentHeight) heightMatch = false;
              if (heightMatch && map.color) validColors.add(map.color);
            });
        }

        return {
          heights: Array.from(validHeights).sort((a, b) => a - b),
          colors: Array.from(validColors).sort()
        };
    }

    function updateFields() {
        const type = getRadioValue("type");
        const subtype = getRadioValue("subtype") || ""; 
        const selectedHeight = heightInputHidden ? heightInputHidden.value : "";
        const selectedColor = colorInputHidden ? colorInputHidden.value : ""; 
        const selectedPoles = getRadioValue("poles");
        const selectedPoleSize = getRadioValue("pole_size");
        const isTile = (subtype === 'tegel');

        const { heights, colors } = getValidOptions(type, subtype, selectedHeight, selectedColor);

        // Onderconstructie
        if (isTile) {
            if(polesWrapper) polesWrapper.style.display = "none";
            if(poleSizeWrapper) poleSizeWrapper.style.display = "none";
        } else {
            if(polesWrapper) polesWrapper.style.display = "block";
            if(polesContainer && polesContainer.innerHTML.trim() === "") {
                 renderCards(polesContainer, "poles", POLE_OPTIONS, selectedPoles || "none");
            } else if (polesContainer) {
                 renderCards(polesContainer, "poles", POLE_OPTIONS, selectedPoles);
            }
            const currentPoleVal = getRadioValue("poles");
            if (currentPoleVal === "with") {
                if(poleSizeWrapper) poleSizeWrapper.style.display = "block";
                if(poleSizeContainer) renderCards(poleSizeContainer, "pole_size", POLE_SIZE_OPTIONS, selectedPoleSize || "40x40");
            } else {
                if(poleSizeWrapper) poleSizeWrapper.style.display = "none";
            }
        }

        // Dikte (of, bij bamboe vlonderplanken: breedte)
        if ((heights.length === 0 && subtype !== 'bangkirai') || isTile) {
            if(heightWrapper) heightWrapper.style.display = "none";
            if(heightInputHidden) heightInputHidden.value = "0";
        } else {
            if(heightWrapper) heightWrapper.style.display = "block";

            const isPlank = isBamboePlank(type, subtype);
            const heightLabel = heightWrapper ? heightWrapper.querySelector('label') : null;
            if (heightLabel) heightLabel.textContent = isPlank ? 'Maat plank (breedte)' : 'Dikte plank';

            let heightOptions;
            if (isPlank) {
                // Toon per breedte ook de bijbehorende planklengte, zodat duidelijk is
                // dat dit unieke, losse producten zijn (geen lengte-varianten van elkaar).
                const mappings = (typeof HHDC2 !== 'undefined' && HHDC2.config) ? Object.values(HHDC2.config.mappings) : [];
                heightOptions = heights.map(w => {
                    const map = mappings.find(m => m.type === 'bamboe' && m.subtype === 'plank' && m.width_mm == w && (!selectedColor || m.color === selectedColor));
                    const lengte = map && map.product_length_mm ? map.product_length_mm : null;
                    return {
                        value: w,
                        label: `${w} mm breed`,
                        desc: lengte ? `Planklengte ${lengte} mm` : "Vlonderplank"
                    };
                });
            } else {
                heightOptions = heights.map(h => ({
                    value: h,
                    label: `${h} mm`,
                    desc: "Dikte"
                }));
            }

            // Tijdelijk: 27mm bij Bangkirai altijd tonen, maar uitverkocht
            if (subtype === 'bangkirai') {
                const existingIndex = heightOptions.findIndex(o => String(o.value) === "27");
                const outOfStockOpt = { value: 27, label: "27 mm", desc: "Dikte", disabled: true, note: "Voorlopig uitverkocht" };

                if (existingIndex !== -1) {
                    heightOptions[existingIndex] = outOfStockOpt;
                } else {
                    heightOptions.push(outOfStockOpt);
                    heightOptions.sort((a, b) => a.value - b.value);
                }
            }

            if(heightContainer) renderCards(heightContainer, "height_radio", heightOptions, selectedHeight);

            // Alleen auto-select als er precies 1 SELECTEERBARE (niet-disabled) optie is
            const selectableHeights = heightOptions.filter(o => !o.disabled);
            if (selectableHeights.length === 1 && !selectedHeight) {
                if(heightInputHidden) heightInputHidden.value = selectableHeights[0].value;
                if(heightContainer) renderCards(heightContainer, "height_radio", heightOptions, selectableHeights[0].value);
            }
        }

        // Kleuren
        if (colors.length > 0) {
            if(colorWrapper) colorWrapper.style.display = "block";
            const colorOptions = colors.map(c => {
                const cleanName = c.charAt(0).toUpperCase() + c.slice(1).replace(/_/g, " ");
                const imgUrl = COLOR_IMAGES[c] || `https://via.placeholder.com/300/cccccc/ffffff?text=${cleanName}`;
                return { value: c, label: cleanName, img: imgUrl };
            });
            if(colorContainer) renderCards(colorContainer, "color_radio", colorOptions, selectedColor);
        } else {
            if(colorWrapper) colorWrapper.style.display = "none";
            if(colorInputHidden) colorInputHidden.value = "";
        }
    }

    // --- EVENT LISTENERS ---
    form.addEventListener("change", function(e) {
        const target = e.target;
        const name = target.name;
        if (name === "type") {
            if(colorInputHidden) colorInputHidden.value = "";
            if(heightInputHidden) heightInputHidden.value = "";
            updateSubtypeOptions(); 
            return;
        }
        if (name === "subtype") {
            if(colorInputHidden) colorInputHidden.value = "";
            if(heightInputHidden) heightInputHidden.value = "";
            updateFields();
            return;
        }
        if (name === "color_radio") {
            if(colorInputHidden) colorInputHidden.value = target.value;
            updateFields();
        }
        if (name === "height_radio") {
            if(heightInputHidden) heightInputHidden.value = target.value;
            updateFields(); 
        }
        if (name === "poles") {
            updateFields(); 
        }
    });

    // --- VALIDATIE ---
    function validateCurrentSlide() {
        const activeSlide = form.querySelector(".hh-dc-slide.active");
        if (!activeSlide) return true;
        const step = activeSlide.dataset.step;

        if (step === "1") {
            if (!getRadioValue("type")) { alert("Kies materiaal."); return false; }
            if (subtypeWrapper && subtypeWrapper.style.display !== "none" && !getRadioValue("subtype")) { 
                alert("Kies uitvoering."); return false; 
            }
        }
        if (step === "3") {
            if (colorWrapper && colorWrapper.style.display !== "none" && colorInputHidden && !colorInputHidden.value) { 
                alert("Kies kleur."); return false; 
            }
            if (heightWrapper && heightWrapper.style.display !== "none" && heightInputHidden && !heightInputHidden.value) { 
                alert("Kies dikte."); return false; 
            }
            if (polesWrapper && polesWrapper.style.display !== "none" && !getRadioValue("poles")) { 
                alert("Kies onderconstructie."); return false; 
            }
        }

        const inputs = activeSlide.querySelectorAll("input:not([type='radio']):not([type='hidden'])");
        let valid = true;
        for (let input of inputs) {
             if (input.offsetParent !== null && !input.checkValidity()) {
                input.reportValidity();
                valid = false;
                break; 
            }
        }
        return valid;
    }

    function showSlide(n) {
        slides.forEach(slide => slide.classList.remove("active"));
        stepDots.forEach(dot => dot.classList.remove("active"));
        const activeSlide = form.querySelector(`.hh-dc-slide[data-step="${n}"]`);
        if(activeSlide) activeSlide.classList.add("active");
        for(let i=0; i < n; i++) { if(stepDots[i]) stepDots[i].classList.add("active"); }
        const pct = (n / 4) * 100; 
        if(progressFill) progressFill.style.width = pct + "%";
        prevBtn.disabled = (n === 1);
        if (n >= totalSteps) { nextBtn.style.display = "none"; calcBtn.style.display = "inline-block"; }
        else { nextBtn.style.display = "inline-block"; calcBtn.style.display = "none"; }
    }

    nextBtn.addEventListener("click", () => { if (validateCurrentSlide() && currentStep < totalSteps) { currentStep++; showSlide(currentStep); } });
    prevBtn.addEventListener("click", () => { if (currentStep > 1) { currentStep--; showSlide(currentStep); } });
    
    restartBtn.addEventListener("click", () => {
        form.style.display = "block";
        resultContainer.style.display = "none";
        if(navButtons) navButtons.style.display = "flex"; 
        currentStep = 1;
        showSlide(1);
        form.reset();
        if(subtypeContainer) subtypeContainer.innerHTML = "";
        if(subtypeWrapper) subtypeWrapper.style.display = "none";
        if(colorContainer) colorContainer.innerHTML = "";
        if(colorWrapper) colorWrapper.style.display = "none";
        if(heightContainer) heightContainer.innerHTML = "";
        if(heightWrapper) heightWrapper.style.display = "none";
        if(polesContainer) polesContainer.innerHTML = "";
        if(colorInputHidden) colorInputHidden.value = "";
        if(heightInputHidden) heightInputHidden.value = "";
        updateSubtypeOptions(); 
    });

    // --- SUBMIT ---
    form.addEventListener("submit", async function (e) {
        e.preventDefault();
        if (!validateCurrentSlide()) return;

        calcBtn.disabled = true;
        calcBtn.textContent = "Berekenen...";

        const payload = {
            type: getRadioValue("type"),
            subtype: getRadioValue("subtype") || "",
            length: parseFloat(document.getElementById("dc-length").value),
            width: parseFloat(document.getElementById("dc-width").value),
            height: parseInt(heightInputHidden ? heightInputHidden.value : 0, 10) || 0,
            color: colorInputHidden ? colorInputHidden.value : "",
            poles: getRadioValue("poles") || "none",
            pole_size: getRadioValue("pole_size") || ""
        };

        try {
            const resp = await fetch(HHDC2.rest.base + "/calc", {
                method: "POST", 
                headers: { "Content-Type": "application/json", "X-WP-Nonce": HHDC2.nonce },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            
            calcBtn.disabled = false;
            calcBtn.textContent = "Bereken materiaal";

            if (!data.success) { 
                alert(data.message); 
                return; 
            }

            // UI voorbereiden op resultaten
            form.style.display = "none";
            if(navButtons) navButtons.style.display = "none"; 
            resultContainer.style.display = "block";
            document.querySelectorAll(".step-dot").forEach(d => d.classList.add("active"));
            if(progressFill) progressFill.style.width = "100%";

            const lines = data.data.lines;
            const advice = data.data.advice; // Haal het nieuwe advice object op uit de Calculator return

            // 1. Bouw de header en de ADVICE BOX (Toggle) op
            let html = `
                <div class="hh-dc-summary-header">
                    <h4>Jouw materiaallijst</h4>
                    <p>Oppervlakte: <strong>${data.data.surface_m2} m²</strong></p>
                </div>

                <div class="hh-dc-advice-box">
                    <button type="button" class="hh-dc-advice-toggle" onclick="this.nextElementSibling.classList.toggle('active'); this.classList.toggle('open');">
                        🛠️ Bekijk jouw persoonlijke leg- en zaaginstructie
                    </button>
                    <div class="hh-dc-advice-content"> <h5>Hoe ga je te werk?</h5>
                        <ul>
                            <li><strong>Onderconstructie:</strong> Plaats de regels met een hart-op-hart afstand van <strong>${advice.spacing} cm</strong>.</li>
                            <li><strong>Fundering:</strong> Plaats de piketpalen (indien gekozen) om de meter onder de regels.</li>
                            <li><strong>Zaagadvies:</strong> ${advice.saw_instruction || "Plaats de planken volgens een wildverband of de aangegeven lengtes."}</li>
                            <li><strong>Leggen:</strong> Houd rekening met een tussenruimte van ca. 5mm tussen de planken voor de natuurlijke werking van het materiaal.</li>
                        </ul>
                        <p style="margin-top:10px;"><small><em>Tip: Begin altijd in een hoek en controleer regelmatig of je nog haaks werkt.</em></small></p>
                    </div>
                </div>

                <div class="hh-dc-results-grid">`;

            // 2. Materiaallijst items toevoegen
            lines.forEach((line) => {
                const imgUrl = line.image || 'https://via.placeholder.com/100'; 
                const title = line.title || (line.meta && line.meta._hh_dc_summary ? line.meta._hh_dc_summary : 'Product');
                const note = line.cutting_note ? `<div class="hh-dc-cutting-note">${line.cutting_note}</div>` : '';
                
                html += `
                    <div class="hh-dc-item-card">
                        <div class="hh-dc-item-img">
                            <img src="${imgUrl}" alt="${title}" />
                        </div>
                        <div class="hh-dc-item-content">
                            <div class="hh-dc-item-header" style="display:flex; justify-content:space-between;">
                                <span class="hh-dc-title">${title}</span>
                                <span class="hh-dc-qty">${line.qty}x</span>
                            </div>
                            ${note}
                        </div>
                    </div>`;
            });

            html += "</div>";
            
            // Resultaten in de box plaatsen
            resultBox.innerHTML = html;

            // Knoppen klaarmaken voor actie
            addToCartBtn.dataset.lines = JSON.stringify(lines);
            addToCartBtn.style.display = "inline-block";
            addToCartBtn.disabled = false;
            addToCartBtn.textContent = "In winkelmand plaatsen";

        } catch (err) { 
            console.error("Berekeningsfout:", err); 
            calcBtn.disabled = false; 
            alert("Er ging iets mis bij het ophalen van het resultaat."); 
        }
    });
    
    // --- ADD TO CART MET VOORRAAD CHECK ---
    addToCartBtn.addEventListener("click", async function () {
        const lines = JSON.parse(addToCartBtn.dataset.lines || "[]");
        if (!lines.length) return;
        addToCartBtn.disabled = true;
        addToCartBtn.textContent = "Voorraad controleren...";
        try {
            const resp = await fetch(HHDC2.rest.base + "/add-to-cart", {
                method: "POST", 
                headers: { "Content-Type": "application/json", "X-WP-Nonce": HHDC2.nonce },
                body: JSON.stringify({ lines })
            });
            const data = await resp.json();
            if (data.out_of_stock) {
                resultBox.innerHTML = `
                    <div class="hh-dc-out-of-stock-msg">
                        <h4>Niet alles op voorraad</h4>
                        <p>Helaas zijn de volgende producten momenteel niet (voldoende) op voorraad:</p>
                        <ul class="hh-dc-failed-list">
                            ${data.items.map(item => `<li><strong>${item}</strong></li>`).join('')}
                        </ul>
                        <p>Neem contact met ons op via de onderstaande knop. We kunnen dit pakket vaak alsnog voor u regelen!</p>
                        <a href="${generateMailtoLink(data.items)}" class="hh-dc-btn primary" style="text-decoration:none; display:inline-block; margin-top:10px;">📩 Stuur mijn aanvraag per mail</a>
                    </div>
                `;
                addToCartBtn.style.display = "none";
                return;
            }
            if (data.success) {
                window.location.href = data.cart_url;
            } else {
                alert("Fout: " + data.message);
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = "In winkelmand plaatsen";
            }
        } catch (err) {
            console.error(err);
            addToCartBtn.disabled = false;
            alert("Er ging iets mis bij het controleren van de voorraad.");
        }
    });

    // Helper functie voor de mailto link
    function generateMailtoLink(failedItems) {
        const subject = encodeURIComponent("Aanvraag vlonderpakket - Voorraad assistentie");
        const length = document.getElementById("dc-length").value;
        const width = document.getElementById("dc-width").value;
        let bodyText = `Beste Haarlemse Houthandel,\n\n`;
        bodyText += `Ik wilde via de calculator een vlonder bestellen van ${length}m x ${width}m.\n`;
        bodyText += `Helaas kreeg ik een melding dat de volgende producten niet op voorraad zijn:\n`;
        failedItems.forEach(item => bodyText += `- ${item}\n`);
        bodyText += `\nKunt u mij helpen met een passende oplossing of een levertijd indicatie?\n\nMet vriendelijke groet,`;
        return `mailto:info@haarlemsehouthandel.nl?subject=${subject}&body=${encodeURIComponent(bodyText)}`;
    }
    
    updateSubtypeOptions(); 
    showSlide(1);
});