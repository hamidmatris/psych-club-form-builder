document.addEventListener("DOMContentLoaded", function() {
    const builder = document.getElementById("pcfb-builder");

    // نمونه ابزارها
    const tools = [
        {type:"text", label:"تکست باکس"},
        {type:"email", label:"ایمیل"},
        {type:"number", label:"عددی"},
        {type:"date", label:"تاریخ"},
        {type:"checkbox", label:"چک‌باکس"},
        {type:"radio", label:"رادیو"},
        {type:"select", label:"انتخابی"}
    ];

    // ایجاد ستون ابزار و پیش‌نمایش
    const toolsDiv = document.createElement("div");
    toolsDiv.className = "pcfb-tools";
    tools.forEach(t=>{
        const div = document.createElement("div");
        div.className = "pcfb-tool";
        div.dataset.type = t.type;
        div.textContent = t.label;
        div.draggable = true;
        toolsDiv.appendChild(div);
    });

    const previewDiv = document.createElement("div");
    previewDiv.id = "pcfb-preview";
    previewDiv.className = "pcfb-preview";
    previewDiv.innerHTML = "<h3>پیش‌نمایش فرم</h3><p style='color:#666;'>ابزارها را بکشید و اینجا رها کنید.</p>";

    builder.appendChild(toolsDiv);
    builder.appendChild(previewDiv);

    let fieldCount = 0;

    function updateJSON() {
        const fields = previewDiv.querySelectorAll(".pcfb-field");
        const data = Array.from(fields).map(f=>({
            type: f.dataset.type,
            label: f.dataset.label,
            options: Array.from(f.querySelectorAll("input.option-input, select option")).map(o=>o.value)
        }));
        console.log("JSON فرم:", JSON.stringify(data, null, 2));
    }

    function addField(type) {
        fieldCount++;
        const fieldWrapper = document.createElement("div");
        fieldWrapper.className = "pcfb-field";
        fieldWrapper.dataset.type = type;
        fieldWrapper.dataset.label = type;

        let label = document.createElement("label");
        label.textContent = type + ": ";
        const input = document.createElement("input");
        input.type = type;
        if(type==="checkbox" || type==="radio") input.name = type+fieldCount;
        label.appendChild(input);
        fieldWrapper.appendChild(label);

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.textContent = "❌";
        removeBtn.addEventListener("click", ()=>{
            fieldWrapper.remove();
            fieldCount--;
            updateJSON();
        });
        fieldWrapper.appendChild(removeBtn);

        previewDiv.appendChild(fieldWrapper);
        updateJSON();
    }

    // Drag & Drop
    toolsDiv.querySelectorAll(".pcfb-tool").forEach(tool=>{
        tool.addEventListener("dragstart", e=>{
            e.dataTransfer.setData("type", tool.dataset.type);
        });
    });

    previewDiv.addEventListener("dragover", e=>{
        e.preventDefault();
        previewDiv.style.background="#eef";
    });
    previewDiv.addEventListener("dragleave", e=>{
        previewDiv.style.background="#fff";
    });
    previewDiv.addEventListener("drop", e=>{
        e.preventDefault();
        previewDiv.style.background="#fff";
        const type = e.dataTransfer.getData("type");
        addField(type);
    });
});
