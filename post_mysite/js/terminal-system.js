/**
 * Advanced Terminal Command System
 * نظام الأوامر الطرفية المتقدم
 * 
 * Features:
 * - Command history with up/down arrows
 * - Auto-completion
 * - Command aliases
 * - File system navigation
 * - System information
 * - User management
 * - File operations
 */

class TerminalSystem {
    constructor() {
        this.commands = new Map();
        this.history = [];
        this.historyIndex = -1;
        this.currentPath = '/';
        this.aliases = new Map();
        this.isOpen = false;
        this.terminalElement = null;
        this.inputElement = null;
        this.outputElement = null;
        
        this.initializeCommands();
        this.initializeAliases();
    }

    /**
     * Initialize all available commands
     */
    initializeCommands() {
        // File system commands
        this.commands.set('ls', {
            description: 'List directory contents',
            usage: 'ls [path]',
            handler: this.listDirectory.bind(this)
        });

        this.commands.set('cd', {
            description: 'Change directory',
            usage: 'cd <path>',
            handler: this.changeDirectory.bind(this)
        });

        this.commands.set('pwd', {
            description: 'Print working directory',
            usage: 'pwd',
            handler: this.printWorkingDirectory.bind(this)
        });

        this.commands.set('cat', {
            description: 'Display file contents',
            usage: 'cat <filename>',
            handler: this.displayFile.bind(this)
        });

        this.commands.set('mkdir', {
            description: 'Create directory',
            usage: 'mkdir <dirname>',
            handler: this.createDirectory.bind(this)
        });

        this.commands.set('rm', {
            description: 'Remove file or directory',
            usage: 'rm <path>',
            handler: this.removeFile.bind(this)
        });

        this.commands.set('cp', {
            description: 'Copy file or directory',
            usage: 'cp <source> <destination>',
            handler: this.copyFile.bind(this)
        });

        this.commands.set('mv', {
            description: 'Move or rename file/directory',
            usage: 'mv <source> <destination>',
            handler: this.moveFile.bind(this)
        });

        // System commands
        this.commands.set('clear', {
            description: 'Clear terminal screen',
            usage: 'clear',
            handler: this.clearScreen.bind(this)
        });

        this.commands.set('help', {
            description: 'Show available commands',
            usage: 'help [command]',
            handler: this.showHelp.bind(this)
        });

        this.commands.set('history', {
            description: 'Show command history',
            usage: 'history',
            handler: this.showHistory.bind(this)
        });

        this.commands.set('whoami', {
            description: 'Display current user',
            usage: 'whoami',
            handler: this.showCurrentUser.bind(this)
        });

        this.commands.set('date', {
            description: 'Display current date and time',
            usage: 'date',
            handler: this.showDate.bind(this)
        });

        this.commands.set('uptime', {
            description: 'Show system uptime',
            usage: 'uptime',
            handler: this.showUptime.bind(this)
        });

        this.commands.set('ps', {
            description: 'Show running processes',
            usage: 'ps',
            handler: this.showProcesses.bind(this)
        });

        this.commands.set('top', {
            description: 'Show system resource usage',
            usage: 'top',
            handler: this.showSystemStats.bind(this)
        });

        // File exchange system commands
        this.commands.set('files', {
            description: 'List available files in exchange system',
            usage: 'files [options]',
            handler: this.listExchangeFiles.bind(this)
        });

        this.commands.set('upload', {
            description: 'Upload file to exchange system',
            usage: 'upload <filepath>',
            handler: this.uploadFile.bind(this)
        });

        this.commands.set('download', {
            description: 'Download file from exchange system',
            usage: 'download <file_id>',
            handler: this.downloadFile.bind(this)
        });

        this.commands.set('purchase', {
            description: 'Purchase file from exchange system',
            usage: 'purchase <file_id>',
            handler: this.purchaseFile.bind(this)
        });

        this.commands.set('balance', {
            description: 'Show user balance and points',
            usage: 'balance',
            handler: this.showBalance.bind(this)
        });

        this.commands.set('transactions', {
            description: 'Show transaction history',
            usage: 'transactions [options]',
            handler: this.showTransactions.bind(this)
        });

        // Network commands
        this.commands.set('ping', {
            description: 'Test network connectivity',
            usage: 'ping <host>',
            handler: this.pingHost.bind(this)
        });

        this.commands.set('curl', {
            description: 'Make HTTP request',
            usage: 'curl <url> [options]',
            handler: this.makeHttpRequest.bind(this)
        });

        // Utility commands
        this.commands.set('grep', {
            description: 'Search text in files',
            usage: 'grep <pattern> <file>',
            handler: this.searchInFile.bind(this)
        });

        this.commands.set('find', {
            description: 'Find files and directories',
            usage: 'find <path> -name <pattern>',
            handler: this.findFiles.bind(this)
        });

        this.commands.set('sort', {
            description: 'Sort lines of text',
            usage: 'sort <file>',
            handler: this.sortFile.bind(this)
        });

        this.commands.set('wc', {
            description: 'Count lines, words, characters',
            usage: 'wc <file>',
            handler: this.countFile.bind(this)
        });

        // Admin commands
        this.commands.set('users', {
            description: 'List system users (admin only)',
            usage: 'users',
            handler: this.listUsers.bind(this)
        });

        this.commands.set('kill', {
            description: 'Terminate process (admin only)',
            usage: 'kill <pid>',
            handler: this.killProcess.bind(this)
        });

        this.commands.set('reboot', {
            description: 'Reboot system (admin only)',
            usage: 'reboot',
            handler: this.rebootSystem.bind(this)
        });
    }

    /**
     * Initialize command aliases
     */
    initializeAliases() {
        this.aliases.set('ll', 'ls -la');
        this.aliases.set('la', 'ls -a');
        this.aliases.set('l', 'ls');
        this.aliases.set('..', 'cd ..');
        this.aliases.set('...', 'cd ../..');
        this.aliases.set('h', 'history');
        this.aliases.set('c', 'clear');
        this.aliases.set('?', 'help');
        this.aliases.set('cls', 'clear');
        this.aliases.set('dir', 'ls');
        this.aliases.set('del', 'rm');
        this.aliases.set('copy', 'cp');
        this.aliases.set('move', 'mv');
        this.aliases.set('ren', 'mv');
    }

    /**
     * Open terminal interface
     */
    open() {
        if (this.isOpen) return;

        this.createTerminalInterface();
        this.isOpen = true;
        this.inputElement.focus();
        this.printWelcomeMessage();
    }

    /**
     * Close terminal interface
     */
    close() {
        if (!this.isOpen) return;

        if (this.terminalElement) {
            this.terminalElement.remove();
        }
        this.isOpen = false;
    }

    /**
     * Toggle terminal visibility
     */
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    /**
     * Create terminal interface
     */
    createTerminalInterface() {
        // Create terminal container
        this.terminalElement = document.createElement('div');
        this.terminalElement.className = 'terminal-container';
        this.terminalElement.innerHTML = `
            <div class="terminal-header">
                <div class="terminal-title">
                    <i class="fas fa-terminal"></i>
                    <span>Terminal</span>
                </div>
                <div class="terminal-controls">
                    <button class="terminal-btn minimize" onclick="terminalSystem.minimize()">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="terminal-btn close" onclick="terminalSystem.close()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="terminal-body">
                <div class="terminal-output" id="terminalOutput"></div>
                <div class="terminal-input-line">
                    <span class="terminal-prompt">
                        <span class="user">${this.getCurrentUser()}</span>@<span class="host">buttcry</span>:<span class="path">${this.currentPath}</span>$
                    </span>
                    <input type="text" class="terminal-input" id="terminalInput" autocomplete="off" spellcheck="false">
                </div>
            </div>
        `;

        // Add to page
        document.body.appendChild(this.terminalElement);

        // Get references
        this.outputElement = document.getElementById('terminalOutput');
        this.inputElement = document.getElementById('terminalInput');

        // Add event listeners
        this.inputElement.addEventListener('keydown', this.handleKeyDown.bind(this));
        this.inputElement.addEventListener('input', this.handleInput.bind(this));

        // Add terminal styles
        this.addTerminalStyles();
    }

    /**
     * Add terminal styles
     */
    addTerminalStyles() {
        if (document.getElementById('terminalStyles')) return;

        const style = document.createElement('style');
        style.id = 'terminalStyles';
        style.textContent = `
            .terminal-container {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 80%;
                max-width: 800px;
                height: 70%;
                max-height: 600px;
                background: #1a1a1a;
                border: 2px solid #00ff00;
                border-radius: 8px;
                box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
                z-index: 10000;
                display: flex;
                flex-direction: column;
                font-family: 'Courier New', monospace;
                animation: terminalSlideIn 0.3s ease-out;
            }

            @keyframes terminalSlideIn {
                from {
                    opacity: 0;
                    transform: translate(-50%, -60%);
                }
                to {
                    opacity: 1;
                    transform: translate(-50%, -50%);
                }
            }

            .terminal-header {
                background: #2a2a2a;
                padding: 8px 12px;
                border-bottom: 1px solid #00ff00;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 6px 6px 0 0;
            }

            .terminal-title {
                color: #00ff00;
                font-weight: bold;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .terminal-controls {
                display: flex;
                gap: 4px;
            }

            .terminal-btn {
                width: 20px;
                height: 20px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 10px;
                transition: all 0.2s;
            }

            .terminal-btn.minimize {
                background: #ffaa00;
                color: #000;
            }

            .terminal-btn.close {
                background: #ff4444;
                color: #fff;
            }

            .terminal-btn:hover {
                transform: scale(1.1);
            }

            .terminal-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .terminal-output {
                flex: 1;
                padding: 12px;
                overflow-y: auto;
                color: #00ff00;
                font-size: 14px;
                line-height: 1.4;
                background: #0a0a0a;
            }

            .terminal-output::-webkit-scrollbar {
                width: 8px;
            }

            .terminal-output::-webkit-scrollbar-track {
                background: #1a1a1a;
            }

            .terminal-output::-webkit-scrollbar-thumb {
                background: #00ff00;
                border-radius: 4px;
            }

            .terminal-input-line {
                display: flex;
                align-items: center;
                padding: 8px 12px;
                background: #2a2a2a;
                border-top: 1px solid #00ff00;
                border-radius: 0 0 6px 6px;
            }

            .terminal-prompt {
                color: #00ff00;
                margin-right: 8px;
                white-space: nowrap;
                font-size: 14px;
            }

            .terminal-prompt .user {
                color: #00aaff;
            }

            .terminal-prompt .host {
                color: #ffaa00;
            }

            .terminal-prompt .path {
                color: #ff00ff;
            }

            .terminal-input {
                flex: 1;
                background: transparent;
                border: none;
                color: #00ff00;
                font-family: 'Courier New', monospace;
                font-size: 14px;
                outline: none;
                caret-color: #00ff00;
            }

            .terminal-input::placeholder {
                color: #666;
            }

            .terminal-line {
                margin: 2px 0;
                word-wrap: break-word;
            }

            .terminal-line.error {
                color: #ff4444;
            }

            .terminal-line.success {
                color: #44ff44;
            }

            .terminal-line.info {
                color: #4444ff;
            }

            .terminal-line.warning {
                color: #ffaa00;
            }

            .terminal-line.command {
                color: #00aaff;
            }

            .terminal-line.output {
                color: #cccccc;
            }

            .terminal-minimized {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 200px;
                height: 40px;
                background: #1a1a1a;
                border: 1px solid #00ff00;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 12px;
                cursor: pointer;
                z-index: 9999;
                animation: terminalMinimize 0.3s ease-out;
            }

            @keyframes terminalMinimize {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .terminal-minimized .terminal-title {
                color: #00ff00;
                font-size: 12px;
            }

            .terminal-minimized .terminal-btn {
                width: 16px;
                height: 16px;
                font-size: 8px;
            }

            .terminal-autocomplete {
                position: absolute;
                background: #2a2a2a;
                border: 1px solid #00ff00;
                border-radius: 4px;
                max-height: 200px;
                overflow-y: auto;
                z-index: 10001;
                min-width: 200px;
            }

            .terminal-autocomplete-item {
                padding: 8px 12px;
                cursor: pointer;
                color: #00ff00;
                border-bottom: 1px solid #1a1a1a;
            }

            .terminal-autocomplete-item:hover,
            .terminal-autocomplete-item.selected {
                background: #00ff00;
                color: #000;
            }

            .terminal-autocomplete-item:last-child {
                border-bottom: none;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Handle keyboard input
     */
    handleKeyDown(event) {
        switch (event.key) {
            case 'Enter':
                event.preventDefault();
                this.executeCommand();
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.navigateHistory(-1);
                break;
            case 'ArrowDown':
                event.preventDefault();
                this.navigateHistory(1);
                break;
            case 'Tab':
                event.preventDefault();
                this.handleTabCompletion();
                break;
            case 'Escape':
                event.preventDefault();
                this.close();
                break;
        }
    }

    /**
     * Handle input changes for auto-completion
     */
    handleInput(event) {
        // Auto-completion logic can be added here
    }

    /**
     * Execute command
     */
    executeCommand() {
        const input = this.inputElement.value.trim();
        if (!input) return;

        // Add to history
        this.history.push(input);
        this.historyIndex = this.history.length;

        // Display command
        this.printLine(`$ ${input}`, 'command');

        // Clear input
        this.inputElement.value = '';

        // Process command
        this.processCommand(input);
    }

    /**
     * Process command input
     */
    processCommand(input) {
        const parts = input.split(' ');
        const command = parts[0].toLowerCase();
        const args = parts.slice(1);

        // Check for aliases
        const actualCommand = this.aliases.get(command) || input;

        if (actualCommand !== input) {
            // Handle alias
            this.processCommand(actualCommand);
            return;
        }

        // Check if command exists locally
        if (this.commands.has(command)) {
            try {
                this.commands.get(command).handler(args);
            } catch (error) {
                this.printLine(`Error: ${error.message}`, 'error');
            }
        } else {
            // Try to execute command via API
            this.executeRemoteCommand(command, args);
        }
    }

    /**
     * Execute command via remote API
     */
    async executeRemoteCommand(command, args) {
        try {
            this.printLine(`Executing remote command: ${command}...`, 'info');
            
            const response = await fetch('terminal-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    command: command,
                    args: args
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.printLine(data.output, data.type || 'output');
            } else {
                this.printLine(data.error || 'Unknown error occurred', 'error');
            }
        } catch (error) {
            this.printLine(`Network error: ${error.message}`, 'error');
        }
    }

    /**
     * Navigate command history
     */
    navigateHistory(direction) {
        if (this.history.length === 0) return;

        this.historyIndex += direction;
        
        if (this.historyIndex < 0) {
            this.historyIndex = 0;
        } else if (this.historyIndex >= this.history.length) {
            this.historyIndex = this.history.length;
            this.inputElement.value = '';
            return;
        }

        this.inputElement.value = this.history[this.historyIndex];
    }

    /**
     * Handle tab completion
     */
    handleTabCompletion() {
        const input = this.inputElement.value.trim();
        const parts = input.split(' ');
        const current = parts[parts.length - 1];

        // Find matching commands
        const matches = Array.from(this.commands.keys())
            .filter(cmd => cmd.startsWith(current))
            .slice(0, 10);

        if (matches.length === 1) {
            // Auto-complete
            parts[parts.length - 1] = matches[0];
            this.inputElement.value = parts.join(' ') + ' ';
        } else if (matches.length > 1) {
            // Show options
            this.printLine(`\n${matches.join('  ')}`, 'info');
        }
    }

    /**
     * Print line to terminal
     */
    printLine(text, type = 'output') {
        const line = document.createElement('div');
        line.className = `terminal-line ${type}`;
        line.textContent = text;
        this.outputElement.appendChild(line);
        this.outputElement.scrollTop = this.outputElement.scrollHeight;
    }

    /**
     * Print multiple lines
     */
    printLines(lines, type = 'output') {
        lines.forEach(line => this.printLine(line, type));
    }

    /**
     * Print welcome message
     */
    printWelcomeMessage() {
        const welcome = [
            'Welcome to ButtCry Terminal System',
            'Type "help" for available commands',
            'Type "exit" or press ESC to close terminal',
            ''
        ];
        this.printLines(welcome, 'info');
    }

    /**
     * Get current user
     */
    getCurrentUser() {
        // This should be replaced with actual user data
        return 'user';
    }

    // Command implementations
    listDirectory(args) {
        const path = args[0] || this.currentPath;
        this.printLine(`Contents of ${path}:`, 'info');
        this.printLine('file1.txt  file2.txt  directory1/', 'output');
        this.printLine('file3.pdf  file4.doc', 'output');
    }

    changeDirectory(args) {
        if (args.length === 0) {
            this.currentPath = '/';
        } else {
            const newPath = args[0];
            if (newPath === '..') {
                const parts = this.currentPath.split('/');
                parts.pop();
                this.currentPath = parts.join('/') || '/';
            } else if (newPath.startsWith('/')) {
                this.currentPath = newPath;
            } else {
                this.currentPath = this.currentPath === '/' ? `/${newPath}` : `${this.currentPath}/${newPath}`;
            }
        }
        this.updatePrompt();
        this.printLine(`Changed to: ${this.currentPath}`, 'success');
    }

    printWorkingDirectory() {
        this.printLine(this.currentPath, 'output');
    }

    displayFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: cat <filename>', 'error');
            return;
        }
        this.printLine(`Contents of ${args[0]}:`, 'info');
        this.printLine('This is a sample file content...', 'output');
    }

    createDirectory(args) {
        if (args.length === 0) {
            this.printLine('Usage: mkdir <dirname>', 'error');
            return;
        }
        this.printLine(`Created directory: ${args[0]}`, 'success');
    }

    removeFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: rm <path>', 'error');
            return;
        }
        this.printLine(`Removed: ${args[0]}`, 'success');
    }

    copyFile(args) {
        if (args.length < 2) {
            this.printLine('Usage: cp <source> <destination>', 'error');
            return;
        }
        this.printLine(`Copied ${args[0]} to ${args[1]}`, 'success');
    }

    moveFile(args) {
        if (args.length < 2) {
            this.printLine('Usage: mv <source> <destination>', 'error');
            return;
        }
        this.printLine(`Moved ${args[0]} to ${args[1]}`, 'success');
    }

    clearScreen() {
        this.outputElement.innerHTML = '';
    }

    showHelp(args) {
        if (args.length > 0) {
            const command = args[0];
            if (this.commands.has(command)) {
                const cmd = this.commands.get(command);
                this.printLine(`${command}: ${cmd.description}`, 'info');
                this.printLine(`Usage: ${cmd.usage}`, 'output');
            } else {
                this.printLine(`Command not found: ${command}`, 'error');
            }
        } else {
            this.printLine('Available commands:', 'info');
            this.printLine('');
            
            const categories = {
                'File System': ['ls', 'cd', 'pwd', 'cat', 'mkdir', 'rm', 'cp', 'mv'],
                'System': ['clear', 'help', 'history', 'whoami', 'date', 'uptime', 'ps', 'top'],
                'File Exchange': ['files', 'upload', 'download', 'purchase', 'balance', 'transactions'],
                'Network': ['ping', 'curl'],
                'Utilities': ['grep', 'find', 'sort', 'wc'],
                'Admin': ['users', 'kill', 'reboot']
            };

            Object.entries(categories).forEach(([category, commands]) => {
                this.printLine(`${category}:`, 'info');
                commands.forEach(cmd => {
                    if (this.commands.has(cmd)) {
                        this.printLine(`  ${cmd} - ${this.commands.get(cmd).description}`, 'output');
                    }
                });
                this.printLine('');
            });
        }
    }

    showHistory() {
        this.printLine('Command History:', 'info');
        this.history.forEach((cmd, index) => {
            this.printLine(`${index + 1}  ${cmd}`, 'output');
        });
    }

    showCurrentUser() {
        this.printLine(this.getCurrentUser(), 'output');
    }

    showDate() {
        const now = new Date();
        this.printLine(now.toString(), 'output');
    }

    showUptime() {
        this.printLine('System uptime: 2 days, 14 hours, 32 minutes', 'output');
    }

    showProcesses() {
        this.printLine('PID    COMMAND', 'info');
        this.printLine('1234   node server.js', 'output');
        this.printLine('5678   php-fpm', 'output');
        this.printLine('9012   nginx', 'output');
    }

    showSystemStats() {
        this.printLine('System Resource Usage:', 'info');
        this.printLine('CPU: 25%', 'output');
        this.printLine('Memory: 60%', 'output');
        this.printLine('Disk: 45%', 'output');
    }

    listExchangeFiles(args) {
        this.printLine('Available Files in Exchange System:', 'info');
        this.printLine('ID  Name                Size    Price  Owner', 'output');
        this.printLine('1   document.pdf        2.5MB   100    user1', 'output');
        this.printLine('2   image.jpg           1.2MB   50     user2', 'output');
        this.printLine('3   archive.zip         5.8MB   200    user3', 'output');
    }

    uploadFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: upload <filepath>', 'error');
            return;
        }
        this.printLine(`Uploading ${args[0]}...`, 'info');
        this.printLine('File uploaded successfully!', 'success');
    }

    downloadFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: download <file_id>', 'error');
            return;
        }
        this.printLine(`Downloading file ID: ${args[0]}...`, 'info');
        this.printLine('Download completed!', 'success');
    }

    purchaseFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: purchase <file_id>', 'error');
            return;
        }
        this.printLine(`Purchasing file ID: ${args[0]}...`, 'info');
        this.printLine('Purchase successful!', 'success');
    }

    showBalance() {
        this.printLine('User Balance:', 'info');
        this.printLine('Points: 1,250', 'output');
        this.printLine('Credits: $25.00', 'output');
    }

    showTransactions(args) {
        this.printLine('Recent Transactions:', 'info');
        this.printLine('Date       Type      Amount  Description', 'output');
        this.printLine('2024-01-15 Purchase  100     document.pdf', 'output');
        this.printLine('2024-01-14 Sale      50      image.jpg', 'output');
    }

    pingHost(args) {
        if (args.length === 0) {
            this.printLine('Usage: ping <host>', 'error');
            return;
        }
        this.printLine(`Pinging ${args[0]}...`, 'info');
        this.printLine('PONG - 25ms', 'success');
    }

    makeHttpRequest(args) {
        if (args.length === 0) {
            this.printLine('Usage: curl <url>', 'error');
            return;
        }
        this.printLine(`Making request to ${args[0]}...`, 'info');
        this.printLine('HTTP/1.1 200 OK', 'output');
        this.printLine('Content-Type: text/html', 'output');
    }

    searchInFile(args) {
        if (args.length < 2) {
            this.printLine('Usage: grep <pattern> <file>', 'error');
            return;
        }
        this.printLine(`Searching for "${args[0]}" in ${args[1]}:`, 'info');
        this.printLine('Line 5: Found match here', 'output');
    }

    findFiles(args) {
        this.printLine('Searching for files...', 'info');
        this.printLine('./documents/file1.txt', 'output');
        this.printLine('./documents/file2.txt', 'output');
    }

    sortFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: sort <file>', 'error');
            return;
        }
        this.printLine(`Sorting ${args[0]}...`, 'info');
        this.printLine('File sorted successfully', 'success');
    }

    countFile(args) {
        if (args.length === 0) {
            this.printLine('Usage: wc <file>', 'error');
            return;
        }
        this.printLine(`Counting ${args[0]}:`, 'info');
        this.printLine('Lines: 25, Words: 150, Characters: 1,200', 'output');
    }

    listUsers() {
        this.printLine('System Users:', 'info');
        this.printLine('user1  admin', 'output');
        this.printLine('user2  user', 'output');
        this.printLine('user3  user', 'output');
    }

    killProcess(args) {
        if (args.length === 0) {
            this.printLine('Usage: kill <pid>', 'error');
            return;
        }
        this.printLine(`Killing process ${args[0]}...`, 'info');
        this.printLine('Process terminated', 'success');
    }

    rebootSystem() {
        this.printLine('Rebooting system...', 'warning');
        this.printLine('System will restart in 10 seconds', 'warning');
    }

    /**
     * Update terminal prompt
     */
    updatePrompt() {
        const promptElement = document.querySelector('.terminal-prompt');
        if (promptElement) {
            promptElement.innerHTML = `
                <span class="user">${this.getCurrentUser()}</span>@<span class="host">buttcry</span>:<span class="path">${this.currentPath}</span>$
            `;
        }
    }

    /**
     * Minimize terminal
     */
    minimize() {
        if (this.terminalElement) {
            this.terminalElement.style.display = 'none';
            
            // Create minimized version
            const minimized = document.createElement('div');
            minimized.className = 'terminal-minimized';
            minimized.innerHTML = `
                <div class="terminal-title">
                    <i class="fas fa-terminal"></i>
                    <span>Terminal</span>
                </div>
                <button class="terminal-btn close" onclick="terminalSystem.close()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            minimized.addEventListener('click', () => {
                minimized.remove();
                this.terminalElement.style.display = 'flex';
                this.inputElement.focus();
            });
            
            document.body.appendChild(minimized);
        }
    }
}

// Global terminal instance
let terminalSystem;

// Initialize terminal system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    terminalSystem = new TerminalSystem();
    
    // Add keyboard shortcut to open terminal (Ctrl+`)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === '`') {
            e.preventDefault();
            terminalSystem.toggle();
        }
    });
});

// Export for global use
window.TerminalSystem = TerminalSystem;
window.terminalSystem = terminalSystem;
