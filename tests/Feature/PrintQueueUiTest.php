<?php

use Symfony\Component\Process\Process;

function queueUiSource(string $path): string
{
    return file_get_contents(resource_path('js/'.$path));
}

function runQueueUiScript(string $script): void
{
    $harness = <<<'JS'
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parse, compileScript } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as vue from 'vue';
import * as queue from './resources/js/lib/queue.ts';
import * as prints from './resources/js/lib/prints.ts';
const visits = [];
const router = Object.fromEntries(['patch', 'delete'].map(method => [method, (...args) => visits.push({ method, args })]));
function loadQueuePage(name, props) {
    const filename = 'resources/js/pages/admin/queue/' + name + '.vue';
    const descriptor = parse(fs.readFileSync(filename, 'utf8'), { filename }).descriptor;
    const compiled = compileScript(descriptor, { id: name });
    const javascript = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
    const module = { exports: {} };
    const require = name => {
        if (name === 'vue') return { ...vue, watch: () => {} };
        if (name === '@/lib/queue') return queue;
        if (name === '@/lib/prints') return prints;
        if (name === '@inertiajs/vue3') return { router, usePage: () => ({ props: {} }), useForm: data => {
            const form = vue.reactive({ ...data, errors: {}, processing: false, isDirty: false });
            form.transform = transform => { form.payload = transform(form); return form; };
            form.post = (...args) => visits.push({ method: 'post', args });
            return form;
        } };
        return {};
    };
    vm.runInNewContext(javascript, { module, exports: module.exports, require, route: (...args) => args, confirm: () => true, HTMLInputElement: class {} });
    return module.exports.default.setup(props, { expose: () => {} });
}
JS;
    $process = new Process(['node', '--input-type=module', '-e', $harness."\n".$script], base_path());
    $process->mustRun();
    expect($process->getExitCode())->toBe(0);
}

it('keeps plate count drafts editable and clamps only when committed', function () {
    runQueueUiScript(<<<'JS'
const page = loadQueuePage('Create', { prefill: null, linkableRequests: [] });
page.setPlateCount(3);
page.plateCount.value = '';
assert.equal(page.plateCount.value, '');
assert.equal(page.form.plates.length, 3);
page.plateCount.value = '12';
page.commitPlateCount();
assert.equal(page.form.plates.length, 12);
page.plateCount.value = '';
page.commitPlateCount();
assert.equal(page.form.plates.length, 1);
page.plateCount.value = '99';
page.form.title = 'Helmet';
page.submit();
assert.equal(page.form.plates.length, 50);
assert.equal(page.form.payload.plates.length, 50);
assert.equal(visits.at(-1).method, 'post');
JS);
});

it('tracks the pending move direction and keeps errors with the moved row', function () {
    runQueueUiScript(<<<'JS'
const page = loadQueuePage('Index', { items: [], doneItems: [], totals: {}, nowPrinting: null, upNext: null });
page.move(7, 'down');
assert.equal(page.isMoving(7, 'down'), true);
assert.equal(page.isMoving(7, 'up'), false);
assert.equal(page.isMoving(8, 'down'), false);
const options = visits.at(-1).args[2];
options.onError({ direction: 'Cannot move this item.' });
options.onFinish();
assert.equal(page.isMoving(7, 'down'), false);
assert.equal(page.moveError.value.itemId, 7);
assert.equal(page.moveError.value.message, 'Cannot move this item.');
page.move(8, 'top');
assert.equal(page.moveError.value, null);
assert.equal(page.isMoving(8, 'top'), true);
JS);
});

it('scopes plate spinners and errors to the attempted action and isolates item deletion errors', function () {
    runQueueUiScript(<<<'JS'
const item = { id: 1, title: 'Helmet', linked_request: null, for_whom: '', needed_by_date: null, filament: '', notes: '', plates: [], plate_counts: { queued: 0 } };
const page = loadQueuePage('Show', { item, printerBusy: false, printingPlate: null, linkableRequests: [] });
const plate = { id: 2, name: 'Plate 2' };
for (const action of ['start', 'up', 'down', 'destroy']) {
    page.runPlate(plate, action);
    assert.equal(page.isPlatePending(2, action), true);
    assert.equal(page.isPlatePending(3, action), false);
    assert.equal(page.isPlatePending(2, 'finish'), false);
    const options = visits.at(-1).args.at(-1);
    options.onError({ plate: 'Finish the printing plate first.' });
    options.onFinish();
    assert.equal(page.isPlatePending(2, action), false);
    assert.equal(page.plateActionError.value.plateId, 2);
    assert.equal(page.plateActionError.value.action, action);
    assert.equal(page.plateActionError.value.message, 'Finish the printing plate first.');
}
page.runItem('destroy');
const options = visits.at(-1).args.at(-1);
options.onError({ item: 'Finish or requeue the printing plate first.' });
options.onFinish();
assert.equal(page.itemActionError.value.action, 'destroy');
assert.equal(page.itemActionError.value.message, 'Finish or requeue the printing plate first.');
JS);
});

it('places action feedback beside its controls and follows mobile family conventions', function () {
    $index = queueUiSource('pages/admin/queue/Index.vue');
    $row = substr($index, strpos($index, 'v-for="(item, index) in props.items"'));
    expect($row)->toContain('moveError?.itemId === item.id', 'moveError.message', 'isMoving(item.id, action.direction)', 'disabled:cursor-not-allowed', 'disabled:opacity-45');
    expect($index)->toContain('duration-200', "isDoneOpen ? 'Hide' : 'Show'", 'text-white/42 uppercase');

    $show = queueUiSource('pages/admin/queue/Show.vue');
    $tile = substr($show, strpos($show, 'v-for="(plate, index) in plates"'), strpos($show, '<form v-if="editingPlateId') - strpos($show, 'v-for="(plate, index) in plates"'));
    expect($tile)->toContain('grid grid-cols-4 gap-2', 'px-3', 'plateActionError?.plateId === plate.id', 'plateActionError.message', "isPlatePending(plate.id, 'up')", "isPlatePending(plate.id, 'down')", "isPlatePending(plate.id, 'destroy')");
    $dialog = substr($show, strpos($show, '<DialogContent>'), strpos($show, '</DialogContent>') - strpos($show, '<DialogContent>'));
    expect($dialog)->toContain("itemActionError?.action === 'destroy'", 'itemActionError.message');
    expect($show)->toContain('Finish or requeue the printing plate first.', 'inline-flex items-center gap-2');

    $create = queueUiSource('pages/admin/queue/Create.vue');
    expect($create)->toContain('min-h-12', '@blur="commitPlateCount"', 'text-right text-sm font-semibold text-white', 'disabled:opacity-45');
    expect($create)->not->toContain('luminous-input mt-3', 'luminous-textarea mt-3');
    expect($create)->toMatch('/<label[^>]*for="add_to_top"[^>]*>[\s\S]*?Add to top[\s\S]*?<input[^>]*id="add_to_top"/');
});

it('keeps the admin desktop and five-item dock in the decided order', function () {
    $source = queueUiSource('layouts/LuminousAppLayout.vue');
    expect($source)->toContain("'queue'", "route('admin.print-queue.index')", 'min-h-12');
    expect($source)->toContain("dockNav.value.length > 4 ? 'min-w-0 flex-auto px-1 tracking-[0.06em]' : 'flex-1 px-2 tracking-[0.16em]'");
    preg_match('/<Link\s+v-for="item in dockNav"[\s\S]*?class="([^"]+)"/', $source, $dockLink);
    expect($dockLink[1])->not->toContain('tracking-[0.16em]');
    $desktop = substr($source, strpos($source, 'const desktopNav'), strpos($source, 'const dockNav') - strpos($source, 'const desktopNav'));
    expect($desktop)->toContain("label: 'Dashboard'")->not->toContain("label: 'Home'");
    expect(substr_count($source, "route('admin.print-queue.index')"))->toBe(2);
    $dock = substr($source, strpos($source, 'const dockNav'));
    $previous = -1;
    foreach (['Home', 'Queue', 'Requests', 'Users', 'Settings'] as $label) {
        $position = strpos($dock, "label: '".$label."'");
        expect($position)->not->toBeFalse()->toBeGreaterThan($previous);
        $previous = $position;
    }
});

it('renders the queue list with shared cards and visible reorder actions', function () {
    $source = queueUiSource('pages/admin/queue/Index.vue');
    expect($source)->toContain('active-nav="queue"', 'PrintQueueSlotCard', "label: 'Up'", "label: 'Down'", "label: 'Top'", 'grid grid-cols-3 gap-2', 'border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary', 'luminous-panel px-5 py-10 text-center', 'preserveScroll: true', 'remainingLabel', 'neededByUrgency', 'isDoneOpen = ref(false)');
    expect(strpos($source, 'kind="now"'))->toBeLessThan(strpos($source, 'kind="next"'));
    expect($source)->toMatch('/<Link[^>]+class="inline-flex min-h-11 items-center"[\s\S]*?>Request #/');
    expect($source)->toMatch('/<Link[^>]+class="pill-button pill-button-secondary mt-3 w-full justify-between"[\s\S]*?still needs completing/');
});

it('keeps the create flow masked, mobile first, and server-error aware', function () {
    $source = queueUiSource('pages/admin/queue/Create.vue');
    expect($source)->toContain('placeholder="MM/DD/YYYY"', 'inputmode="numeric"', 'maxlength="10"', 'pill-button pill-button-primary w-full', ':show-dock="false"', 'maskedDateToIso(data.needed_by_date.trim())', 'joinMinutes(plate.hours, plate.minutes)', 'form.plates.every', 'form.processing', 'Math.min(50', 'errors[', 'canSubmit');
    expect($source)->not->toContain('type="date"', 'forceFormData');
    expect(strpos($source, 'for="needed_by_date"'))->toBeLessThan(strpos($source, 'for="plate_count"'));
});

it('keeps detail editing, confirmations, errors, and lifecycle controls', function () {
    $source = queueUiSource('pages/admin/queue/Show.vue');
    expect($source)->toContain('active-nav="queue"', 'DialogContent', 'DialogTrigger as-child', 'confirm(', ':tone="plateStatusTone(plate.status)"', ':label="plateStatusLabel(plate.status)"', 'form.processing || !form.isDirty', 'plateForm.processing || !plateForm.isDirty', 'Reopen this item to add plates.', 'preserveScroll: true', 'placeholder="MM/DD/YYYY"', 'Mark the request complete to notify');
    expect($source)->not->toContain('type="date"');
    expect($source)->toContain('props.printerBusy && props.printingPlate');
    expect(strpos($source, '>Plates</p>'))->toBeLessThan(strpos($source, '>Summary</p>'));
    expect(strpos($source, 'Mark the request complete to notify'))->toBeLessThan(strpos($source, 'v-for="(plate, index) in plates"'));
    expect($source)->toContain('class="inline-flex min-h-11 items-center"');
    preg_match_all('/<button\b[\s\S]*?<\/button>/', $source, $buttons);
    $disabledCount = 0;
    foreach ($buttons[0] as $button) {
        if (str_contains($button, ':disabled=')) {
            $disabledCount++;
            expect($button)->toContain('disabled:cursor-not-allowed', 'disabled:opacity-45');
        }
    }
    expect($disabledCount)->toBeGreaterThanOrEqual(12);
});

it('puts the admin queue cards before dashboard metrics without changing the member branch', function () {
    $source = queueUiSource('pages/Dashboard.vue');
    $region = strpos($source, 'v-if="props.isAdmin && props.queue"');
    $metric = strpos($source, 'v-for="card in metricCards"');
    expect($region)->not->toBeFalse()->toBeLessThan($metric);
    expect(substr($source, $region, $metric - $region))->toContain('PrintQueueSlotCard', "props.queue.nowPrinting ? 'now' : 'next'", 'Open queue');
    expect($source)->toContain('Print queue', 'ListOrdered', 'page.props.flash?.status');
    $flash = strpos($source, 'rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary');
    expect($flash)->not->toBeFalse()->toBeLessThan($region);
});

it('gates request queue links inside the admin workflow and preserves badge defaults', function () {
    $source = queueUiSource('pages/prints/Show.vue');
    $admin = strpos($source, 'v-if="props.can.isAdmin" class="luminous-panel px-5 py-5"');
    $queue = strpos($source, '>Print Queue</p>');
    expect($admin)->not->toBeFalse()->toBeLessThan($queue);
    expect($queue)->toBeLessThan(strpos($source, '>Delivery Check</p>'));
    expect($source)->toContain('admin.print-queue.create', 'Add to queue', 'admin.print-queue.show', 'Printed through the queue.', "props.printRequest.status !== 'complete'");
    expect(queueUiSource('components/luminous/StatusBadge.vue'))->toContain('tone?: string', 'label?: string', 'props.tone ?? statusTone(props.status)', 'props.label ?? statusLabel(props.status)');
});

it('shares printer controls with pending and error handling', function () {
    expect(queueUiSource('components/luminous/PrintQueueSlotCard.vue'))->toContain('disabled:cursor-not-allowed disabled:opacity-45', 'role="alert"', 'luminous-panel px-4 py-4 sm:px-5 sm:py-5', 'Printer is idle.', 'Nothing queued. Add an item to fill the queue.', 'Finish the current plate first.', 'preserveScroll: true', 'onError:', 'onFinish:', 'errors.status', 'LoaderCircle', 'Open item');
});

it('explains the queue data kept after a user purge', function () {
    expect(queueUiSource('pages/admin/users/Show.vue'))->toContain(
        'Completed queue items are deleted.',
        'Active queue items and their plates stay in place, with requester names and',
        'item notes cleared and request-generated titles replaced.',
    );
});

it('executes queue duration and due-date helpers', function () {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { formatMinutes, splitMinutes, joinMinutes, remainingLabel, plateStatusLabel, plateStatusTone } from './resources/js/lib/queue.ts';
import { neededByUrgency } from './resources/js/lib/prints.ts';
assert.equal(formatMinutes(45), '45m');
assert.equal(formatMinutes(133), '2h 13m');
assert.equal(formatMinutes(1620), '1d 3h');
assert.equal(formatMinutes(0), '0m');
assert.equal(formatMinutes(60), '1h');
assert.equal(formatMinutes(1440), '1d');
assert.deepEqual(splitMinutes(null), { hours: '', minutes: '' });
assert.deepEqual(splitMinutes(133), { hours: '2', minutes: '13' });
assert.equal(joinMinutes('', ''), null);
assert.equal(joinMinutes('2', '13'), 133);
assert.equal(joinMinutes('0', '0'), 0);
assert.equal(remainingLabel(320, 2), '5h 20m left + 2 unestimated');
assert.equal(remainingLabel(45, 0), '45m left');
for (const status of ['queued', 'printing', 'done', 'failed']) {
    assert.equal(plateStatusLabel(status), status[0].toUpperCase() + status.slice(1));
    assert.ok(plateStatusTone(status).includes('text-'));
}
assert.deepEqual(neededByUrgency(null), { label: 'No due date', tone: 'text-white/42' });
const today = new Date();
const date = offset => { const value = new Date(today.getFullYear(), today.getMonth(), today.getDate() + offset); return [value.getFullYear(), String(value.getMonth() + 1).padStart(2, '0'), String(value.getDate()).padStart(2, '0')].join('-'); };
assert.equal(neededByUrgency(date(-1)).tone, 'text-rose-300');
assert.ok(neededByUrgency(date(-1)).label.startsWith('Overdue · '));
assert.equal(neededByUrgency(date(7)).tone, 'text-secondary');
assert.equal(neededByUrgency(date(8)).tone, 'text-white/58');
JS;
    $process = new Process(['node', '--input-type=module', '-e', $script], base_path());
    $process->mustRun();
    expect($process->getExitCode())->toBe(0);
});
