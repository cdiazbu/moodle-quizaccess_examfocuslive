/* Generate Moodle-loadable AMD files for standalone development. */
const fs = require('node:fs');
const path = require('node:path');
const {minify} = require('terser');

const root = path.resolve(__dirname, '..');
const source = path.join(root, 'amd', 'src');
const output = path.join(root, 'amd', 'build');
async function build() {
    fs.mkdirSync(output, {recursive: true});
    for (const name of ['student_monitor', 'dashboard']) {
        const filename = `${name}.js`;
        const esModule = fs.readFileSync(path.join(source, filename), 'utf8').replace(/\r\n/g, '\n');
        const body = esModule
            .replace(/^import Ajax from 'core\/ajax';$/m, '')
            .replace(/^export const init = /m, 'const init = ');
        if (body === esModule || /^\s*(import|export)\s/m.test(body)) {
            throw new Error(`Unsupported module syntax in ${filename}`);
        }
        const id = `quizaccess_examfocuslive/${name}`;
        const amd = `define('${id}', ['core/ajax'], function(Ajax) {'use strict';\n${body}\nreturn {init};\n});`;
        const outputName = `${name}.min.js`;
        const result = await minify({[filename]: amd}, {
            ecma: 2021,
            sourceMap: {filename: outputName, url: `${outputName}.map`, includeSources: true},
            format: {comments: /^!|@license|@preserve/},
        });
        fs.writeFileSync(path.join(output, outputName), `${result.code}\n`);
        fs.writeFileSync(path.join(output, `${outputName}.map`), `${result.map}\n`);
    }
}

build().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
});

