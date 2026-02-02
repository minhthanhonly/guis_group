<?php
require_once('../application/loader.php');
$view->heading('顧客インポート');
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card mb-4">
		<div class="card-header d-flex justify-content-between align-items-center">
			<h5 class="card-title mb-0">Excelで顧客を一括登録</h5>
			<a href="<?=ROOT?>customer/index.php" class="btn btn-label-secondary btn-sm">
				<i class="icon-base ti tabler-arrow-left me-1"></i> 顧客一覧へ
			</a>
		</div>
		<div class="card-body">
			<div class="alert alert-info mb-4">
				<strong>使い方:</strong>
				<ol class="mb-0 mt-2">
					<li>下の「ファイルテンプレートをダウンロード」でExcel用CSVを取得し、Excelで開いて編集します。</li>
					<li>1行目はヘッダー（変更しないでください）。2行目以降に顧客データを入力します。</li>
					<li>同じ会社名・支店名・担当者名の組み合わせが既に存在する行は更新されます。存在しない行は新規登録されます。</li>
					<li>必須: category_id, company_name, name。category_idはカテゴリ一覧のIDを指定してください。</li>
				</ol>
			</div>
			<div class="row mb-4">
				<div class="col-md-6">
					<a href="<?=ROOT?>customer/template.php" class="btn btn-outline-primary">
						<i class="icon-base fa fa-download me-1"></i> ファイルテンプレートをダウンロード
					</a>
				</div>
			</div>
			<form id="importForm">
				<div class="mb-4">
					<label class="form-label">Excel/CSVファイルを選択</label>
					<input type="file" class="form-control" id="fileInput" accept=".xlsx,.xls,.csv" required>
				</div>
				<button type="button" class="btn btn-primary" id="btnPreview" disabled>
					<i class="icon-base fa fa-eye me-1"></i> プレビュー
				</button>
			</form>
		</div>
	</div>

	<div class="card mb-4" id="previewCard" style="display: none;">
		<div class="card-header">
			<h5 class="card-title mb-0">プレビュー <span id="previewCount" class="badge bg-primary ms-2">0</span> 件</h5>
		</div>
		<div class="card-body">
			<div class="table-responsive">
				<table class="table table-bordered table-sm">
					<thead id="previewHead"></thead>
					<tbody id="previewBody"></tbody>
				</table>
			</div>
			<div class="mt-3">
				<button type="button" class="btn btn-success" id="btnImport">
					<i class="icon-base fa fa-upload me-1"></i> インポート実行
				</button>
			</div>
		</div>
	</div>

	<div class="card" id="resultCard" style="display: none;">
		<div class="card-header">
			<h5 class="card-title mb-0">インポート結果</h5>
		</div>
		<div class="card-body">
			<div id="resultMessage"></div>
			<div id="resultErrors" class="mt-3" style="display: none;"></div>
		</div>
	</div>
</div>
<?php $view->footing(); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
(function() {
	const fileInput = document.getElementById('fileInput');
	const btnPreview = document.getElementById('btnPreview');
	const btnImport = document.getElementById('btnImport');
	const previewCard = document.getElementById('previewCard');
	const previewHead = document.getElementById('previewHead');
	const previewBody = document.getElementById('previewBody');
	const previewCount = document.getElementById('previewCount');
	const resultCard = document.getElementById('resultCard');
	const resultMessage = document.getElementById('resultMessage');
	const resultErrors = document.getElementById('resultErrors');

	let parsedRows = []; // array of objects (key = column header)

	const HEADER_MAP = {
		'category_id': 'category_id',
		'カテゴリID': 'category_id',
		'company_name': 'company_name',
		'会社名': 'company_name',
		'company_name_kana': 'company_name_kana',
		'会社名(ふりがな)': 'company_name_kana',
		'name': 'name',
		'担当者名': 'name',
		'name_kana': 'name_kana',
		'担当者名(ふりがな)': 'name_kana',
		'branch': 'branch',
		'支店名': 'branch',
		'department': 'department',
		'担当部署': 'department',
		'position': 'position',
		'役職': 'position',
		'title': 'title',
		'敬称': 'title',
		'tel': 'tel',
		'電話番号': 'tel',
		'fax': 'fax',
		'FAX': 'fax',
		'email': 'email',
		'メールアドレス': 'email',
		'zip': 'zip',
		'郵便番号': 'zip',
		'address1': 'address1',
		'住所1': 'address1',
		'address2': 'address2',
		'住所2': 'address2',
		'status': 'status',
		'状況': 'status',
		'guis_department': 'guis_department',
		'自社担当部署': 'guis_department',
		'memo': 'memo',
		'メモ': 'memo'
	};

	function normalizeHeader(str) {
		if (typeof str !== 'string') return '';
		const t = str.toString().trim();
		return HEADER_MAP[t] || t;
	}

	function parseFile(file) {
		return new Promise(function(resolve, reject) {
			const reader = new FileReader();
			reader.onload = function(e) {
				try {
					const data = new Uint8Array(e.target.result);
					const workbook = XLSX.read(data, { type: 'array', raw: true });
					const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
					const json = XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '' });
					if (json.length < 2) {
						reject(new Error('データ行がありません。1行目はヘッダー、2行目以降にデータを入力してください。'));
						return;
					}
					const headers = json[0].map(function(h) { return normalizeHeader(h); });
					const rows = [];
					for (let i = 1; i < json.length; i++) {
						const row = json[i];
						const obj = {};
						headers.forEach(function(h, j) {
							const val = row[j];
							obj[h] = (val !== undefined && val !== null) ? String(val).trim() : '';
						});
						rows.push(obj);
					}
					resolve(rows);
				} catch (err) {
					reject(err);
				}
			};
			reader.onerror = function() { reject(new Error('ファイルの読み込みに失敗しました。')); };
			reader.readAsArrayBuffer(file);
		});
	}

	function renderPreview(rows) {
		if (rows.length === 0) return;
		const sample = rows[0];
		const keys = Object.keys(sample);
		let headHtml = '<tr>';
		keys.forEach(function(k) {
			headHtml += '<th>' + k + '</th>';
		});
		headHtml += '</tr>';
		previewHead.innerHTML = headHtml;
		let bodyHtml = '';
		rows.slice(0, 50).forEach(function(row) {
			bodyHtml += '<tr>';
			keys.forEach(function(k) {
				bodyHtml += '<td>' + (row[k] || '') + '</td>';
			});
			bodyHtml += '</tr>';
		});
		if (rows.length > 50) {
			bodyHtml += '<tr><td colspan="' + keys.length + '" class="text-muted">... 他 ' + (rows.length - 50) + ' 件</td></tr>';
		}
		previewBody.innerHTML = bodyHtml;
		previewCount.textContent = rows.length;
		previewCard.style.display = 'block';
		resultCard.style.display = 'none';
	}

	fileInput.addEventListener('change', function() {
		btnPreview.disabled = !fileInput.files.length;
		previewCard.style.display = 'none';
	});

	btnPreview.addEventListener('click', function() {
		const file = fileInput.files[0];
		if (!file) return;
		btnPreview.disabled = true;
		parseFile(file).then(function(rows) {
			parsedRows = rows;
			renderPreview(rows);
		}).catch(function(err) {
			Notiflix.Report.failure('エラー', err.message || 'ファイルの解析に失敗しました。', 'OK');
		}).finally(function() {
			btnPreview.disabled = false;
		});
	});

	btnImport.addEventListener('click', function() {
		if (parsedRows.length === 0) return;
		btnImport.disabled = true;
		const payload = { rows: parsedRows };
		axios.post('/api/index.php?model=customer&method=import_customers', payload, {
			headers: { 'Content-Type': 'application/json' }
		}).then(function(res) {
			const d = res.data;
			resultCard.style.display = 'block';
			if (d.status === 'success') {
				resultMessage.innerHTML = '<div class="alert alert-success">' +
					'<strong>完了:</strong> 新規登録 ' + (d.inserted || 0) + ' 件、更新 ' + (d.updated || 0) + ' 件。</div>';
				if (d.errors && d.errors.length > 0) {
					resultErrors.style.display = 'block';
					resultErrors.innerHTML = '<div class="alert alert-warning"><strong>一部エラー:</strong><ul class="mb-0">' +
						d.errors.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul></div>';
				} else {
					resultErrors.style.display = 'none';
				}
			} else {
				resultMessage.innerHTML = '<div class="alert alert-danger">' + (d.message_code || d.error || 'エラーが発生しました。') + '</div>';
				resultErrors.style.display = 'none';
			}
		}).catch(function(err) {
			resultCard.style.display = 'block';
			resultMessage.innerHTML = '<div class="alert alert-danger">' + (err.response && err.response.data && (err.response.data.message_code || err.response.data.error)) || err.message || '通信エラー' + '</div>';
			resultErrors.style.display = 'none';
		}).finally(function() {
			btnImport.disabled = false;
		});
	});
})();
</script>
