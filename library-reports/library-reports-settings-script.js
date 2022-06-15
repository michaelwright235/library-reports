document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector("#library-reports-settings-form");

    function initTable() {
        if(Object.keys(JSONdata).length != 0)
            for(const line of JSONdata) {
                addLine(line['id'], line['name'], line['rights']);
            }
    }
    
    function addLine(id = '', name = '', rights= '') {
        const table = document.querySelector("#libraries_table");
        const tr = document.createElement("tr");

        const tdID = document.createElement("td");
        tdID.append(createInput('id', id));

        tr.append(tdID);

        const tdName = document.createElement("td");
        tdName.append(createInput('name', name));
        tr.append(tdName);

        const tdRights = document.createElement("td");
        const selectUsers = document.createElement("select");
        selectUsers.classList.add('rights');
        selectUsers.multiple = true;
        rights = rights.split(',');
        libraryReportsUsers.forEach((e) => {
            let opt = document.createElement("option");
            opt.value = e.id;
            opt.textContent = e.name;
            if(rights.includes(e.id)) opt.selected = true;
            selectUsers.append(opt);
        });
        
        tdRights.append(selectUsers);
        tr.append(tdRights);

        table.append(tr);
    }

    function createInput(name, val = '') {
        const input = document.createElement("input");
        input.type = "text";
        if(val != '') input.value = val;
        input.classList.add(name);
        return input;
    }

    function createJSON() {
        const lines = document.querySelectorAll("#libraries_table > tr");
        let libraries = [];
        for(const line of lines) {
            let params = {};
            params.id = line.querySelector(".id").value;
            if(params.id.trim() == '') continue;

            params.name = line.querySelector(".name").value;
            let rightsSelector = line.querySelector(".rights");
            let selectedRights = [];
            for(o of rightsSelector) {
                if(o.selected) selectedRights.push(o.value);
            };
            params.rights = selectedRights.join(',');

            libraries.push(params);
        }
        return JSON.stringify(libraries);
    }

    form.addEventListener("submit", () => {
        document.getElementById("libraries").value = createJSON();
    });

    document.getElementById('library_plus_btn').addEventListener("click", () => addLine());
    initTable();
});
