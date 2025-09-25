<h2>مدیریت فرم‌ها</h2>

<div style="display:flex; gap:20px;" class="pcfb-builder">
    
    <!-- ابزارها -->
    <div style="flex:1; background:#f9f9f9; padding:15px; border:1px solid #ccc;" class="pcfb-tools">
        <h3>ابزارها</h3>
        <div class="pcfb-tool" draggable="true" data-type="text">📝 تکست باکس</div><br>
        <div class="pcfb-tool" draggable="true" data-type="email">📧 ایمیل</div><br>
        <div class="pcfb-tool" draggable="true" data-type="number">🔢 عددی</div><br>
        <div class="pcfb-tool" draggable="true" data-type="date">📅 تاریخ</div><br>
        <div class="pcfb-tool" draggable="true" data-type="checkbox">✅ چک‌باکس</div><br>
        <div class="pcfb-tool" draggable="true" data-type="radio">🔘 رادیو</div><br>
        <div class="pcfb-tool" draggable="true" data-type="select">⬇️ انتخابی (Select)</div>
    </div>

    <!-- پیش‌نمایش فرم -->
    <div id="pcfb-preview" style="flex:2; min-height:300px; background:#fff; padding:15px; border:1px solid #ccc;" class="pcfb-preview">
        <h3>پیش‌نمایش فرم</h3>
        <p style="color:#666;">ابزارهای مورد نظر را از ستون چپ بکشید و در اینجا رها کنید.</p>
    </div>

</div>

<div style="margin-top: 15px;">
    <button id="pcfb-build-form" style="display:none;">ساخت فرم</button>
    <button id="pcfb-clear">پاک کردن همه</button>
    <h4>JSON فرم فعلی:</h4>
    <pre id="pcfb-json" style="background:#f4f4f4; padding:10px; border:1px solid #ccc;"></pre>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const tools = document.querySelectorAll(".pcfb-tool");
    const preview = document.getElementById("pcfb-preview");
    const clearBtn = document.getElementById("pcfb-clear");
    const buildBtn = document.getElementById("pcfb-build-form");
    const jsonOutput = document.getElementById("pcfb-json");

    let fieldCount = 0;

    function updateJSON() {
        const fields = preview.querySelectorAll(".pcfb-field");
        const data = Array.from(fields).map(f => ({
            type: f.dataset.type,
            label: f.dataset.label || f.dataset.type,
            options: Array.from(f.querySelectorAll("input[type=text].option-input, select option")).map(o => o.value)
        }));
        jsonOutput.textContent = JSON.stringify(data, null, 2);
    }

    function enableFieldSorting() {
        let dragSrcEl = null;
        const fields = preview.querySelectorAll(".pcfb-field");

        fields.forEach(field => {
            field.draggable = true;

            field.addEventListener("dragstart", e => {
                dragSrcEl = field;
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("text/html", field.outerHTML);
                field.classList.add("dragging");
            });

            field.addEventListener("dragover", e => {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                field.style.borderTop = "2px solid #3498db";
            });

            field.addEventListener("dragleave", e => {
                field.style.borderTop = "";
            });

            field.addEventListener("drop", e => {
                e.stopPropagation();
                field.style.borderTop = "";
                if(dragSrcEl !== field) {
                    preview.insertBefore(dragSrcEl, field);
                    updateJSON();
                    enableFieldSorting();
                }
                return false;
            });

            field.addEventListener("dragend", e => {
                field.classList.remove("dragging");
                preview.querySelectorAll(".pcfb-field").forEach(f=>f.style.borderTop="");
            });
        });
    }

    function showBuildButton() { buildBtn.style.display = "inline-block"; }
    function hideBuildButton() { buildBtn.style.display = "none"; }

    function addField(type) {
        fieldCount++;
        const fieldWrapper = document.createElement("div");
        fieldWrapper.className = "pcfb-field";
        fieldWrapper.dataset.type = type;
        fieldWrapper.dataset.label = type;

        let labels = [];

        if(type === "checkbox" || type === "radio"){
            const mainLabel = document.createElement("div");
            mainLabel.className = "main-field-label";
            mainLabel.textContent = type;
            fieldWrapper.appendChild(mainLabel);

            labels = [];
            for(let i=0; i<2; i++){
                const lbl = document.createElement("label");
                const input = document.createElement("input");
                input.type = type;
                if(type==="radio") input.name = "radio"+fieldCount;
                lbl.appendChild(input);
                lbl.appendChild(document.createTextNode(" گزینه " + (i+1)));
                fieldWrapper.appendChild(lbl);
                labels.push(lbl);
            }
        } else if(type === "select"){
            const lbl = document.createElement("label");
            lbl.textContent = "انتخابی: ";
            const select = document.createElement("select");
            ["گزینه ۱","گزینه ۲"].forEach(optText => {
                const opt = document.createElement("option");
                opt.text = optText;
                select.appendChild(opt);
            });
            lbl.appendChild(select);
            fieldWrapper.appendChild(lbl);
        } else {
            const lbl = document.createElement("label");
            lbl.textContent = type + ": ";
            const input = document.createElement("input");
            input.type = type;
            input.style.width = "100%";
            lbl.appendChild(input);
            fieldWrapper.appendChild(lbl);
        }

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "remove-field";
        removeBtn.textContent = "❌";
        removeBtn.addEventListener("click", ()=>{
            fieldWrapper.remove();
            fieldCount--;
            updateJSON();
            enableFieldSorting();
            if(fieldCount===0) hideBuildButton();
        });

        const editBtn = document.createElement("button");
        editBtn.type = "button";
        editBtn.className = "edit-field";
        editBtn.textContent = "⚙️";

        const settingsDiv = document.createElement("div");
        settingsDiv.className = "field-settings";
        settingsDiv.style.display = "none";
        settingsDiv.style.marginTop = "5px";
        settingsDiv.style.padding = "5px";
        settingsDiv.style.border = "1px solid #ccc";
        settingsDiv.style.background = "#f9f9f9";

        // تنظیمات فیلد
        if(type==="checkbox" || type==="radio"){
            settingsDiv.innerHTML = `<label>اسم فیلد: <input type="text" class="field-label" value="${type}"></label><br>`;
            const mainLabelDiv = fieldWrapper.querySelector(".main-field-label");

            const optionsContainer = document.createElement("div");
            optionsContainer.className = "options-container";
            settingsDiv.appendChild(optionsContainer);

            labels.forEach((lbl,i)=>{
                const optDiv = document.createElement("div");
                optDiv.style.marginBottom = "5px";

                const optInput = document.createElement("input");
                optInput.type = "text";
                optInput.className = "option-input";
                optInput.value = lbl.childNodes[1].textContent.trim();

                const removeOptBtn = document.createElement("button");
                removeOptBtn.type = "button";
                removeOptBtn.textContent = "❌";
                removeOptBtn.style.marginLeft = "5px";

                removeOptBtn.addEventListener("click", ()=>{
                    lbl.remove();
                    optDiv.remove();
                    updateJSON();
                });

                optInput.addEventListener("input", e=>{
                    lbl.childNodes[1].textContent = " " + e.target.value;
                    updateJSON();
                });

                optDiv.appendChild(optInput);
                optDiv.appendChild(removeOptBtn);
                optionsContainer.appendChild(optDiv);
            });

            const addOptionBtn = document.createElement("button");
            addOptionBtn.type = "button";
            addOptionBtn.textContent = "+ گزینه";
            addOptionBtn.style.display = "block";
            addOptionBtn.style.marginTop = "5px";

            addOptionBtn.addEventListener("click", ()=>{
                const newOptLabel = document.createElement("label");
                const inputEl = document.createElement("input");
                inputEl.type = type;
                if(type==="radio") inputEl.name = "radio"+fieldCount;
                newOptLabel.appendChild(inputEl);
                newOptLabel.appendChild(document.createTextNode(" گزینه جدید"));
                fieldWrapper.appendChild(newOptLabel);

                const newOptDiv = document.createElement("div");
                newOptDiv.style.marginBottom = "5px";

                const newOptInput = document.createElement("input");
                newOptInput.type = "text";
                newOptInput.value = "گزینه جدید";

                const newRemoveBtn = document.createElement("button");
                newRemoveBtn.type = "button";
                newRemoveBtn.textContent = "❌";
                newRemoveBtn.style.marginLeft = "5px";

                newRemoveBtn.addEventListener("click", ()=>{
                    newOptLabel.remove();
                    newOptDiv.remove();
                    updateJSON();
                });

                newOptInput.addEventListener("input", e=>{
                    newOptLabel.childNodes[1].textContent = " " + e.target.value;
                    updateJSON();
                });

                newOptDiv.appendChild(newOptInput);
                newOptDiv.appendChild(newRemoveBtn);
                optionsContainer.appendChild(newOptDiv);

                updateJSON();
            });

            settingsDiv.appendChild(addOptionBtn);

            const fieldLabelInput = settingsDiv.querySelector(".field-label");
            fieldLabelInput.addEventListener("input", e=>{
                mainLabelDiv.textContent = e.target.value;
                fieldWrapper.dataset.label = e.target.value;
                updateJSON();
            });

        } else if(type==="select"){
            settingsDiv.innerHTML = `<label>اسم فیلد: <input type="text" class="field-label" value="انتخابی"></label><br>`;
            const selectEl = fieldWrapper.querySelector("select");
            const fieldLabelInput = settingsDiv.querySelector(".field-label");

            const optionsContainer = document.createElement("div");
            optionsContainer.className = "options-container";
            settingsDiv.appendChild(optionsContainer);

            Array.from(selectEl.options).forEach((opt,i)=>{
                const optDiv = document.createElement("div");
                optDiv.style.marginBottom = "5px";

                const optInput = document.createElement("input");
                optInput.type = "text";
                optInput.value = opt.text;
                optInput.className = "option-input";

                const removeOptBtn = document.createElement("button");
                removeOptBtn.type = "button";
                removeOptBtn.textContent = "❌";
                removeOptBtn.style.marginLeft = "5px";

                removeOptBtn.addEventListener("click", ()=>{
                    selectEl.removeChild(selectEl.options[i]);
                    optDiv.remove();
                    updateJSON();
                });

                optInput.addEventListener("input", e=>{
                    selectEl.options[i].text = e.target.value;
                    updateJSON();
                });

                optDiv.appendChild(optInput);
                optDiv.appendChild(removeOptBtn);
                optionsContainer.appendChild(optDiv);
            });

            const addOptionBtn = document.createElement("button");
            addOptionBtn.type = "button";
            addOptionBtn.textContent = "+ گزینه";
            addOptionBtn.style.display = "block";
            addOptionBtn.style.marginTop = "5px";

            addOptionBtn.addEventListener("click", ()=>{
                const newOption = document.createElement("option");
                newOption.text = "گزینه جدید";
                selectEl.appendChild(newOption);

                const newOptDiv = document.createElement("div");
                newOptDiv.style.marginBottom = "5px";

                const newOptInput = document.createElement("input");
                newOptInput.type = "text";
                newOptInput.value = "گزینه جدید";

                const newRemoveBtn = document.createElement("button");
                newRemoveBtn.type = "button";
                newRemoveBtn.textContent = "❌";
                newRemoveBtn.style.marginLeft = "5px";

                newRemoveBtn.addEventListener("click", ()=>{
                    selectEl.removeChild(newOption);
                    newOptDiv.remove();
                    updateJSON();
                });

                newOptInput.addEventListener("input", e=>{
                    newOption.text = e.target.value;
                    updateJSON();
                });

                newOptDiv.appendChild(newOptInput);
                newOptDiv.appendChild(newRemoveBtn);
                optionsContainer.appendChild(newOptDiv);

                updateJSON();
            });

            settingsDiv.appendChild(addOptionBtn);

            fieldLabelInput.addEventListener("input", e=>{
                const lbl = fieldWrapper.querySelector("label");
                if(lbl) lbl.childNodes[0].textContent = e.target.value + ": ";
                fieldWrapper.dataset.label = e.target.value;
                updateJSON();
            });

        } else {
            settingsDiv.innerHTML = `<label>اسم فیلد: <input type="text" class="field-label" value="${type}"></label>`;
            const fieldLabelInput = settingsDiv.querySelector(".field-label");
            fieldLabelInput.addEventListener("input", e=>{
                const lbl = fieldWrapper.querySelector("label");
                if(lbl) lbl.childNodes[0].textContent = e.target.value + ": ";
                fieldWrapper.dataset.label = e.target.value;
                updateJSON();
            });
        }

        editBtn.addEventListener("click", ()=>{
            settingsDiv.style.display = settingsDiv.style.display==="none"?"block":"none";
        });

        fieldWrapper.appendChild(removeBtn);
        fieldWrapper.appendChild(editBtn);
        fieldWrapper.appendChild(settingsDiv);

        preview.appendChild(fieldWrapper);
        enableFieldSorting();
        updateJSON();

        if(fieldCount===1) showBuildButton();
    }

    tools.forEach(tool=>{
        tool.addEventListener("dragstart", e=>{
            e.dataTransfer.setData("type", tool.dataset.type);
        });
    });

    preview.addEventListener("dragover", e=>{
        e.preventDefault();
        preview.style.background="#eef";
    });

    preview.addEventListener("dragleave", e=>{
        preview.style.background="#fff";
    });

    preview.addEventListener("drop", e=>{
        e.preventDefault();
        preview.style.background="#fff";
        const type = e.dataTransfer.getData("type");
        addField(type);
    });

    clearBtn.addEventListener("click", ()=>{
        const fields = preview.querySelectorAll(".pcfb-field");
        fields.forEach(f=>f.remove());
        fieldCount = 0;
        updateJSON();
        hideBuildButton();
    });

});
</script>
