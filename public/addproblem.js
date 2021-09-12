const { Dropdown, EditableSolutions, TipTapForm } = VueComponents;

$(document).ready(() => {

    const isAdd = (addedit === 'add');
    const isEdit = (addedit === 'edit');

    const AddProblemApp = Vue.createApp({
        components: {
            Dropdown, EditableSolutions, TipTapForm
        },
        data: function() {
            return {
                globals: window.globals,

                id: 0,
                title: "",
                topic: 0,
                level: 0,
                question: "",
                solutions: [],
                checked: ''
            };
        },
        methods: {
            checkedSolution(idx) {
                for (let i = 0; i < this.solutions.length; ++i) {
                    if (this.solutions[i].correct) {
                        return (i === idx);
                    }
                }

                return false;
            },

            selectCategory(selection) {
                this.topic = selection.id;
            },

            selectLevel(selection) {
                this.level = selection.id;
            },

            setCorrectSolution(idx) {
                for (let i = 0; i < this.solutions.length; ++i) {
                    delete this.solutions[i].correct;
                }

                this.solutions[idx].correct = true;
            },

            solutionIndex() {
                for (let i = 0; i < this.solutions.length; ++i) {
                    if (this.solutions[i].correct) {
                        return i;
                    }
                }
            },

            cancel() {
                if (isEdit) {
                    window.location = '/problem/' + this.id;
                } else {
                    window.location = '/problems';
                }
            },

            setQuestionBody(json) {
                this.question = json;
            },

            getQuestionBody() {
                return (this.question ? GenerateHTML(this.question, VueHTMLExtensions) : "");
            },

            save() {

                let questionJson = this.question;

                // FIXME: Inject katex in texts w/ {{ }}
                const recurseEl = (el) => {
                    if (el.content instanceof Array) {
                        for (let i = 0; i < el.content.length; ++i) {
                            if (el.content[i].type === "text") {
                                let textParts = [];
                                let textHtml = el.content[i].text;

                                let readFrom = 0;
                                do {
                                    let idxStart = textHtml.indexOf('{{', readFrom),
                                        idxEnd = textHtml.indexOf('}}', readFrom);

                                    if (idxStart === -1 || idxEnd <= idxStart) break;

                                    let katexRaw = textHtml.substr(idxStart + 2, (idxEnd - idxStart) - 2);
                                    // FIXME: Inline?

                                    let start = textHtml.substr(readFrom, idxStart);
                                    if (start.trim() !== "") {
                                        textParts.push({
                                            type: "text",
                                            text: start
                                        });
                                    }

                                    if (katexRaw.trim() !== "") {
                                        textParts.push({
                                            type: "katex",
                                            content: [{ type: "text", text: katexRaw }],
                                            attrs: { inline: true }
                                        });
                                    }

                                    readFrom = idxEnd + 2;
                                } while (true);

                                let end = textHtml.substr(readFrom);
                                if (end.trim() !== "") {
                                    textParts.push({
                                        type: "text",
                                        text: end
                                    });
                                }

                                if (el.content[i].marks) {
                                    for (let j = 0; j < textParts.length; ++j) {
                                        textParts[j].marks = el.content[i].marks;
                                    }
                                }

                                if (textParts.length > 0) {
                                    el.content.splice(i, 1, ...textParts);
                                }
                            } else {
                                recurseEl(el.content[i]);
                            }
                        }
                    }
                };

                recurseEl(questionJson);


                let bodyInflatedJson = questionJson;
                let { child, elements } = JSON_BODY_TO_HTML(bodyInflatedJson);
                let bodyDeflatedHtml = child,
                    bodyDeflatedJson = JSON.stringify(bodyDeflatedHtml);

                let solutions = [];
                for (let i = 0; i < this.solutions.length; ++i) {
                    const solution = {};
                    solution.text = this.solutions[i].text;
                    if (this.solutions[i].correct) {
                        $('#hiddenSolution').val(i);
                        solution.correct = true;
                    }
                    solutions.push(solution);
                }
                let solutionsStr = JSON.stringify({solutions: solutions});
                let body = solutionsStr.length + solutionsStr + bodyDeflatedJson;
                console.log(body);

                this.$refs.formBody.value = body;
                this.$refs.form.submit();
            }
        },
        computed: {
        },

        beforeMount: function() {

            // FIXME: can we use props instead of window global?
            if (isEdit) {
                this.jsonData = ProblemJson;

                this.id    = this.jsonData.id;
                this.title = this.jsonData.title;
                this.topic = this.jsonData.topic;
                this.level = this.jsonData.level;
                //this.author = this.jsonData.author_id;

                const solutionStart = this.jsonData.body.indexOf('{'),
                    solutionLen = parseInt(this.jsonData.body.substr(0, solutionStart)),
                    solutionString = this.jsonData.body.substr(solutionStart, solutionLen),
                    solutionJson = JSON.parse(solutionString);
                this.solutions = solutionJson.solutions;

                let question = this.jsonData.body.substr(solutionLen + solutionStart);
                this.question = ENCODED_TO_JSON(question);

                // FIXME: There's probably a better way to do this
                // Replace Katex tags w/  {{ text }}
                const recurseEl = (el) => {
                    if (el.content instanceof Array) {
                        for (let i = 0; i < el.content.length; ++i) {
                            if (el.content[i].type === "katex") {
                                // FIXME: Inline?
                                el.content[i] = el.content[i].content[0];
                                Assert(el.content[i].type === "text");
                                el.content[i].text = '{{ ' + el.content[i].text + ' }}';
                            } else {
                                recurseEl(el.content[i]);
                            }
                        }
                    }
                };

                recurseEl(this.question);

                for (let i = this.solutions.length; i < 4; ++i) {
                    this.solutions.push({
                        correct: false,
                        text: ""
                    });
                }

                console.log(ProblemJson);
            } else {

                for (let i = 0; i < 4; ++i) {
                    this.solutions.push({
                        correct: i === 0,
                        text: ""
                    });
                }
            }

            // FIXME: Apply Old here
        },
        mounted() {
            this.$refs.editor.setInput(this.question);
        },
        beforeUnmount() {
        },
    });

    const mountedProblem = AddProblemApp.mount('#app');


    // FIXME: Use Vuex or something to avoid this
    window['mountedProblem'] = mountedProblem;
});

