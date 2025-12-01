(function(window, document){
    // Expects sdUploaderArgs localized: { ajax_url, nonce, chunk_size }
    var form = document.getElementById('upload-zip-form');
    var progressContainer = document.getElementById('upload-progress-container');
    var progressBar = document.getElementById('upload-progress-bar');
    var progressText = document.getElementById('upload-progress-text');

    if (!form) return;

    function uuidv4() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var fileInput = form.querySelector('input[type="file"][name="sd_project_zip"]');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
        var file = fileInput.files[0];
        var CHUNK_SIZE = sdUploaderArgs && sdUploaderArgs.chunk_size ? parseInt(sdUploaderArgs.chunk_size) : (20 * 1024 * 1024);

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.innerText = 'Preparing upload...';

        // If file smaller than or equal to chunk size, do standard upload via form submit fallback
        if (file.size <= CHUNK_SIZE) {
            // perform normal upload to existing form flow
            var xhr = new XMLHttpRequest();
            xhr.open('POST', sdUploaderArgs.ajax_url + '?action=upload_progress_check&nonce=' + sdUploaderArgs.nonce, true);
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    progressText.innerText = 'Uploading... ' + Math.round(percent) + '%';
                }
            };
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    progressText.innerText = 'Upload complete!';
                    // start processing
                    startBatchProcessing();
                } else {
                    progressText.innerText = 'Upload failed.';
                }
            };
            var fd = new FormData(form);
            xhr.send(fd);
            return;
        }

        // Chunked flow for large files
        var uploadId = uuidv4();
        var totalSize = file.size;
        var totalChunks = Math.ceil(totalSize / CHUNK_SIZE);
        var currentChunk = 0;
        var maxRetries = 3;

        progressText.innerText = 'Uploading in chunks...';

        function sendChunk(index, attempt) {
            attempt = attempt || 0;
            var start = index * CHUNK_SIZE;
            var end = Math.min(start + CHUNK_SIZE, totalSize);
            var chunk = file.slice(start, end);
            var fd = new FormData();
            fd.append('action', 'sd_upload_chunk');
            fd.append('nonce', sdUploaderArgs.nonce);
            fd.append('upload_id', uploadId);
            fd.append('chunk_index', index);
            fd.append('total_chunks', totalChunks);
            fd.append('total_size', totalSize);
            fd.append('filename', file.name);
            fd.append('chunk', chunk, file.name + '.part.' + index);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', sdUploaderArgs.ajax_url, true);
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var percent = Math.round(((index + 1) / totalChunks) * 100);
                            progressBar.style.width = percent + '%';
                            progressText.innerText = 'Uploading... ' + percent + '%';

                            if (index + 1 < totalChunks) {
                                sendChunk(index + 1);
                            } else {
                                progressText.innerText = 'Upload complete. Assembling...';
                                // Poll for assembly completion via upload status until percent == 100
                                pollAssembly(uploadId, function(){
                                    progressText.innerText = 'Upload assembled. Starting processing...';
                                    startBatchProcessing();
                                });
                            }
                        } else {
                            // server side error
                            if (attempt < maxRetries) {
                                setTimeout(function(){ sendChunk(index, attempt + 1); }, 1000 * Math.pow(2, attempt));
                            } else {
                                progressText.innerText = 'Chunk upload failed: ' + (res.data && res.data.message ? res.data.message : 'server error');
                            }
                        }
                    } catch (err) {
                        if (attempt < maxRetries) {
                            setTimeout(function(){ sendChunk(index, attempt + 1); }, 1000 * Math.pow(2, attempt));
                        } else {
                            progressText.innerText = 'Failed to parse server response.';
                        }
                    }
                } else {
                    if (attempt < maxRetries) {
                        setTimeout(function(){ sendChunk(index, attempt + 1); }, 1000 * Math.pow(2, attempt));
                    } else {
                        progressText.innerText = 'Network error during chunk upload.';
                    }
                }
            };
            xhr.onerror = function() {
                if (attempt < maxRetries) {
                    setTimeout(function(){ sendChunk(index, attempt + 1); }, 1000 * Math.pow(2, attempt));
                } else {
                    progressText.innerText = 'Network error during chunk upload.';
                }
            };
            xhr.send(fd);
        }

        function pollAssembly(uploadId, cb) {
            var attempts = 0;
            function check() {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', sdUploaderArgs.ajax_url + '?action=sd_upload_status&nonce=' + sdUploaderArgs.nonce + '&upload_id=' + encodeURIComponent(uploadId), true);
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                var percent = res.data.percent;
                                progressBar.style.width = percent + '%';
                                progressText.innerText = (percent < 100) ? ('Assembling... ' + percent + '%') : 'Assembly complete';
                                if (percent >= 100) return cb();
                                setTimeout(check, 1000);
                            } else {
                                attempts++;
                                if (attempts < 5) setTimeout(check, 1000);
                                else progressText.innerText = 'Assembly status check failed.';
                            }
                        } catch (err) {
                            attempts++;
                            if (attempts < 5) setTimeout(check, 1000);
                            else progressText.innerText = 'Assembly status parse error.';
                        }
                    } else {
                        attempts++;
                        if (attempts < 5) setTimeout(check, 1000);
                        else progressText.innerText = 'Assembly status request failed.';
                    }
                };
                xhr.send();
            }
            check();
        }

        // kick off
        sendChunk(0);
    });

    function startBatchProcessing() {
        // Prefer server-driven batch processing pump we control here
        pumpBatch();
    }

    // Pump server-side processing by asking the server to process one batch at a time.
    function pumpBatch() {
        var running = false;
        function runOnce() {
            if (running) return;
            running = true;
            var fd = new FormData();
            fd.append('action', 'sd_process_batch');
            fd.append('nonce', sdUploaderArgs.nonce);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', sdUploaderArgs.ajax_url, true);
            xhr.onload = function() {
                running = false;
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            var percent = res.data.percent || 0;
                            progressBar.style.width = percent + '%';
                            progressText.innerText = res.data.status || 'Processing...';
                            if (percent < 100) {
                                // schedule next batch
                                setTimeout(runOnce, 500);
                            }
                        } else {
                            progressText.innerText = 'Processing error: ' + (res.data && res.data.message ? res.data.message : 'server error');
                        }
                    } catch (e) {
                        // parsing error
                        setTimeout(runOnce, 1000);
                    }
                } else {
                    // server/network error -> retry
                    setTimeout(runOnce, 2000);
                }
            };
            xhr.onerror = function() {
                running = false;
                setTimeout(runOnce, 2000);
            };
            xhr.send(fd);
        }
        runOnce();
    }

})(window, document);
