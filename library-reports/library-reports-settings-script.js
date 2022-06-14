document.addEventListener('DOMContentLoaded', () => {
    function initTable() {
        const table = document.querySelector("#libraries_table");
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
        tdRights.append(createInput('rights', rights));
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
            const inputs = line.querySelectorAll("input");
            for(const input of inputs) {
                params[input.className] = input.value;
            }
            if(params['id'] != '') libraries.push(params);
        }
        console.log(JSON.stringify(libraries));
        return JSON.stringify(libraries);
    }

    document.forms[0].onsubmit = function(e) {
        document.getElementById("libraries").value = createJSON();
    };
    document.getElementById('library_plus_btn').onclick = () => {addLine()};
    initTable();
});
