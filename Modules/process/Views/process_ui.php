<?php
defined('EMONCMS_EXEC') or die('Restricted access');
global $path, $settings;
load_language_files(dirname(__DIR__) . '/locale', "process_messages");

// settings.ini parse_ini_file does not convert [0,6,8,10] into an array
// while settings.php engines_hidden will be an array
// we convert here the array form to a string which is then passed below
// to the process ui javascript side of things which coverts to a js array
$engine_hidden = $settings["feed"]['engines_hidden'];
if (is_array($engine_hidden)) $engine_hidden = json_encode($engine_hidden);
?>
<style>
    .modal-processlist {
        width: 94%;
        left: 3%;
        /* (100%-width)/2 */
        margin-left: auto;
        margin-right: auto;
        overflow-y: hidden;
    }

    .modal-processlist .modal-body {
        max-height: none;
        overflow-y: auto;
    }

    #process-table th:nth-of-type(6),
    td:nth-of-type(6) {
        text-align: right;
    }

    #new-feed-tag_autocomplete-list {
        width: 120px
    }

    #process-header-add,
    #process-header-edit {
        font-weight: bold;
        font-size: 16px;
    }
</style>
<script type="text/javascript">
    <?php require "Modules/process/process_langjs.php"; ?>
</script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/misc/autocomplete.js?v=<?php echo $v; ?>"></script>
<link rel="stylesheet" href="<?php echo $path; ?>Lib/misc/autocomplete.css?v=<?php echo $v; ?>">
<script src="<?php echo $path; ?>Modules/process/process.js?v=9"></script>

<div id="process_vue">
    <div id="processlistModal" class="modal hide keyboard modal-processlist" tabindex="-1" role="dialog" aria-labelledby="processlistModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-header">
            <button type="button" class="close" @click="close">×</button>
            <h3><b>{{ input_or_virtual_feed_name }}</b> <?php echo dgettext('process_messages', 'process list setup'); ?></h3>
        </div>
        <div class="modal-body" id="processlist-ui">

            <p><?php echo dgettext('process_messages', 'Processes are executed sequentially with the result value being passed down for further processing to the next processor on this processing list.'); ?></p>

            <!-- Process list controls -->
            <div style="margin-bottom: 10px;">
                <button 
                    class="btn" 
                    :title="selected_processes.length == process_list.length ? 'Unselect all' : 'Select all'"
                    @click="select_all"
                >
                <i :class="selected_processes.length == process_list.length ? 'icon icon-ban-circle' : 'icon icon-check'"></i>
                </button>
                <button class="btn" title="<?php echo _('Cut') ?>" :disabled="!selected_processes.length" @click="cut"><span class="icon">&#9986;</span></button>
                <button class="btn" title="<?php echo _('Copy') ?>" :disabled="!selected_processes.length" @click="copy"><span class="icon">&#9112;</span></button>
                <button class="btn" title="<?php echo _('Paste') ?>" :disabled="!copied_processes.length" @click="paste"><i class="icon icon-briefcase"></i></button>
                <button class="btn" title="<?php echo _('Delete') ?>" :disabled="!selected_processes.length" @click="remove_selected"><i class="icon icon-trash"></i></button>
            </div>

            <!-- No processes message -->
            <div class="alert" v-if="process_list.length==0"><?php echo dgettext('process_messages', 'You have no processes defined'); ?></div>

            <!-- Process table -->
            <table class="table table-hover" v-if="process_list.length > 0">
                <tr>
                    <th></th>
                    <th><?php echo dgettext('process_messages', 'Order'); ?></th>
                    <th style="width:40%;"><?php echo dgettext('process_messages', 'Process'); ?></th>
                    <th style="text-align:right;opacity:.8" title="<?php echo dgettext('process_messages', 'Hover over the short names below to get the full description'); ?>"><i class="icon icon-question-sign"></i></th>
                    <th style="width:40%;"><?php echo dgettext('process_messages', 'Arguments'); ?></th>
                    <th><span class="hidden-md"><?php echo dgettext('process_messages', 'Latest'); ?></span></th>
                    <th colspan='3'><?php echo dgettext('process_messages', 'Actions'); ?></th>
                </tr>

                <tr v-for="(process, index) in process_list" :key="index" v-if="processes_by_key[process.fn]">
                    <!-- Checkbox for selecting processes -->
                    <td><div class="select text-center"><input type="checkbox" :value="index" v-model="selected_processes"></div></td>
                    <!-- Process index -->
                    <td style="text-align:right;">{{ index + 1 }}</td>
                    <!-- Process name -->
                    <td>{{ processes_by_key[process.fn].name }}</td>
                    <!-- Process badge -->
                    <td style="text-align:right">
                        <span v-if="processes_by_key[process.fn].description" :title="strip_html(processes_by_key[process.fn].description)" class="fw-label overflow-hidden label" :class="process.label" style="cursor:help">{{ processes_by_key[process.fn].short }}</span>
                        <span v-else class="fw-label overflow-hidden label label-default">{{ processes_by_key[process.fn].short }}</span>
                    </td>
                    <!-- Process arguments -->
                    <td>
                        <span v-if="processes_by_key[process.fn].args">
                            <span v-for="(arg, arg_index) in processes_by_key[process.fn].args" :key="arg_index">
                                <span v-if="arg.type == ProcessArg.VALUE">
                                    <span class="muted" title="Value"><i class="icon-edit"></i> {{ process.args[arg_index] }}</span>
                                </span>
                                <span v-if="arg.type == ProcessArg.TEXT">
                                    <span class="muted" title="Text"><i class="icon-edit"></i> {{ process.args[arg_index] }}</span>
                                </span>
                                <span v-if="arg.type == ProcessArg.FEEDID">
                                    <span class="muted" title="Feed"><i class="icon-list-alt"></i><span v-if="feeds_by_id[process.args[arg_index]]">{{ feeds_by_id[process.args[arg_index]].tag }}: {{ feeds_by_id[process.args[arg_index]].name }}</span></span>
                                </span>
                            </span>
                        </span>
                    </td>

                    <td><small title="Last recorded 2 value" class="muted">(0.00)</small></td>
                    <td style="white-space:nowrap;">
                        <a title="Move down" @click="moveby(index,1)"><i class="icon-arrow-down" style="cursor:pointer"></i></a>
                        <a title="Move up" @click="moveby(index,-1)"><i class="icon-arrow-up" style="cursor:pointer"></i></a>
                    </td>
                    <td><a class="edit-process" title="Edit"><i class="icon-pencil" style="cursor:pointer"></i></a></td>
                    <td><a title="Delete" @click="remove(index)"><i class="icon-trash" style="cursor:pointer"></i></a></td>
                </tr>
            </table>

            <table class="table">
                <tr>
                    <td>
                        <p>
                            <span id="process-header-add"><?php echo dgettext('process_messages', 'Add process'); ?>:
                                <a href="#" onclick="selectProcess(event)" class="label label-info" data-processid="process__log_to_feed">log</a>
                                <a href="#" onclick="selectProcess(event)" class="label label-info" data-processid="process__power_to_kwh">kwh</a>
                                <a href="#" onclick="selectProcess(event)" class="label label-warning" data-processid="process__add_input">+inp</a>
                            </span>
                            <span id="process-header-edit"><?php echo dgettext('process_messages', 'Edit process'); ?>:</span>
                        </p>

                        <!-- Process select dropdown -->
                        <select id="select-process" class="input-large" v-model="selected_process" @change="processSelectChange">
                            <optgroup v-for="(processes, group) in context_only_processes_by_group" :label="group">
                                <option v-for="(process, process_key) in processes" :value="process_key">{{ process.name }}</option>
                            </optgroup>
                        </select>

                        <span v-for="(arg, index) in args">

                            <span v-if="arg.type == ProcessArg.VALUE">
                                <div class="input-prepend">
                                    <span class="add-on value-select-label">{{ arg.name }} <i class="icon icon-question-sign" :title="arg.desc"></i></span>
                                    <input type="text" v-model.number="arg.value" class="input-medium" placeholder="<?php echo dgettext('process_messages', 'Type value...'); ?>" />
                                </div>
                            </span>

                            <span v-if="arg.type == ProcessArg.TEXT">
                                <div class="input-prepend">
                                    <span class="add-on text-select-label">{{ arg.name }} <i class="icon icon-question-sign" :title="arg.desc"></i></span>
                                    <input type="text" v-model="arg.value" class="input-large" placeholder="<?php echo dgettext('process_messages', 'Type text...'); ?>" />
                                </div>
                            </span>

                            <span v-if="arg.type == ProcessArg.INPUTID">
                                <div class="input-prepend">
                                    <span class="add-on input-select-label"><?php echo dgettext('process_messages', 'Input'); ?></span>
                                    <div class="btn-group">
                                        <select class="input-medium" v-model="arg.value">
                                            <optgroup v-for="(inputs,node_name) in inputs_by_node" :label="'Node '+node_name">
                                                <option v-for="input in inputs" :value="input.id">{{ input.name }}: {{ input.description }}</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                            </span>

                            <span v-if="arg.type == ProcessArg.SCHEDULEID">
                                <div class="input-prepend">
                                    <span class="add-on schedule-select-label"><?php echo dgettext('process_messages', 'Schedule'); ?></span>
                                    <div class="btn-group">
                                        <select class="input-large" v-model="arg.value">
                                            <option v-for="schedule in schedules" :value="schedule.id">{{ schedule.id }}: {{ schedule.name }}</option>
                                        </select>
                                    </div>
                                </div>
                            </span>

                            <span v-if="arg.type == ProcessArg.FEEDID">
                                <div class="input-prepend">
                                    <span class="add-on feed-select-label"><?php echo dgettext('process_messages', 'Feed'); ?></span>
                                    <div class="btn-group">
                                        <select class="input-medium" style="border-bottom-right-radius: 0;border-top-right-radius: 0;" v-model="arg.value">
                                            <!-- feeds by tag -->
                                            <option value="-1">CREATE NEW:</option>
                                            <optgroup v-for="(feeds, tag) in feeds_by_tag" :label="tag">
                                                <option v-for="feed in feeds" :value="feed.id" v-if="feed.engine!=7">{{ feed.name }}</option>
                                            </optgroup>
                                        </select>
                                        <span v-if="arg.value == -1">
                                            <div class="autocomplete">
                                                <!-- autocomplete uses jquery which is a bit of a hack here, but it works -->
                                                <!-- removed pattern="[a-zA-Z0-9-_: ]+" giving error -->
                                                <input v-model="arg.new_feed_tag" id="new-feed-tag" @click="feedSelectChange" @change="feedSelectChange" type="text" required style="width:4em; border-right: none; border-bottom-right-radius: 0; border-top-right-radius: 0;" title="<?php echo dgettext('process_messages', 'Please enter a feed tag consisting of alphabetical letters, A-Z a-z 0-9 - _ : and spaces'); ?>" placeholder="<?php echo dgettext('process_messages', 'Tag'); ?>" />
                                            </div>
                                            <!-- removed pattern="[a-zA-Z0-9-_: ]+" giving error -->
                                            <input v-model="arg.new_feed_name" id="new-feed-name" type="text" required style="width:6em" title="<?php echo dgettext('process_messages', 'Please enter a feed name consisting of alphabetical letters, A-Z a-z 0-9 - _ : and spaces'); ?>" placeholder="<?php echo dgettext('process_messages', 'Name'); ?>" />
                                        </span>
                                    </div>
                                </div>
                                <div class="input-prepend" v-if="arg.value == -1">
                                    <span class="add-on feed-engine-label"><?php echo dgettext('process_messages', 'Engine'); ?></span>
                                    <div class="btn-group">
                                        <select class="input-medium" v-model.number="arg.new_feed_engine">
                                            <?php foreach (Engine::get_all_descriptive() as $engine) { ?>
                                                <option v-if="arg.engines && arg.engines.includes(<?php echo $engine["id"]; ?>)" value="<?php echo $engine["id"]; ?>"><?php echo $engine["description"]; ?></option>
                                            <?php } ?>
                                        </select>

                                        <select class="input-mini" v-model.number="arg.new_feed_interval" v-if="[1,4,5,6].includes(Number(arg.new_feed_engine))">
                                            <option value=""><?php echo dgettext('process_messages', 'Select interval'); ?></option>
                                            <?php foreach (Engine::available_intervals() as $i) { ?>
                                                <option value="<?php echo $i["interval"]; ?>"><?php echo dgettext('process_messages', $i["description"]); ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php if (isset($settings["feed"]["mysqltimeseries"]) && isset($settings["feed"]["mysqltimeseries"]["generic"]) && !$settings["feed"]["mysqltimeseries"]["generic"]) { ?>
                                            <!-- remove pattern="[a-zA-Z0-9_]+" giving error -->
                                            <input v-if="[0,8].includes(Number(arg.new_feed_engine))" v-model="arg.new_feed_table_name" type="text" style="width:6em" title="<?php echo dgettext('process_messages', 'Please enter a table name consisting of alphabetical letters, A-Z a-z 0-9 and _ characters'); ?>" placeholder="<?php echo dgettext('process_messages', 'Table'); ?>" />
                                        <?php } ?>
                                    </div>
                                </div>
                            </span>
                        </span>

                        <span id="type-btn-add">
                            <div class="input-prepend">
                                <button id="process-add" @click="processAdd" class="btn btn-info" style="border-radius: 4px;"><?php echo dgettext('process_messages', 'Add'); ?></button>
                            </div>
                        </span>
                        <span id="type-btn-edit" style="display:none">
                            <div class="input-prepend">
                                <button id="process-edit" class="btn btn-info" style="border-radius: 4px;"><?php echo dgettext('process_messages', 'Edit'); ?></button>
                            </div>
                            <div class="input-prepend">
                                <button id="process-cancel" class="btn" style="border-radius: 4px;"><?php echo dgettext('process_messages', 'Cancel'); ?></button>
                            </div>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="alert alert-info" v-if="processes_by_key[selected_process]">
                            <p><b>{{ processes_by_key[selected_process].name }}</b></p>
                            <span v-if="processes_by_key[selected_process].description" v-html="processes_by_key[selected_process].description"></span>
                            <p v-else><b>No process description available for process {{ processes_by_key[selected_process].name }}</b></p>

                            <p v-if="processes_by_key[selected_process].help_url"><a :href="processes_by_key[selected_process].help_url" target="_blank"><?php echo dgettext('process_messages', 'Click here for additional information about this process'); ?></a></p>
                            <p v-if="processes_by_key[selected_process].nochange"><b><?php echo dgettext('process_messages', 'Output:'); ?></b> <?php echo dgettext('process_messages', 'Does NOT modify value passed onto next process step.'); ?></p>
                            <p v-else><b><?php echo dgettext('process_messages', 'Output:'); ?></b> <?php echo dgettext('process_messages', 'Modified value passed onto next process step.'); ?></p>
                            <p v-if="processes_by_key[selected_process].requireredis"><b><?php echo dgettext('process_messages', 'REDIS:'); ?></b> <?php echo dgettext('process_messages', 'Requires REDIS.'); ?></p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="modal-footer">
            <button class="btn" @click="close"><?php echo dgettext('process_messages', 'Close'); ?></button>

            <button class="btn btn-success" style="float:right" v-if="state == 'not_modified'">
                <span><?php echo dgettext('process_messages', 'Not modified'); ?></span>
            </button>

            <button @click="save" class="btn btn-warning" style="float:right" v-if="state == 'modified'">
                <span><?php echo dgettext('process_messages', 'Changed, press to save'); ?></span>
            </button>

            <button class="btn btn-success" style="float:right" v-if="state == 'saved'" disabled>
                <span><?php echo dgettext('process_messages', 'Saved'); ?></span>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript" src="<?php echo $path; ?>Modules/process/Views/process_ui.js?v=<?php echo $v; ?>"></script>

<script>
    // processlist_ui.engines_hidden = <?php echo $engine_hidden; ?>;

    process_vue.has_redis = <?php echo ($settings["redis"]["enabled"] ? '1' : '0'); ?>;

    $(window).resize(function() {
        process_vue.adjustModal()
    });
</script>