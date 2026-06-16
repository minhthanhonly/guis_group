document.addEventListener('DOMContentLoaded', function() {
    const checkin = document.getElementById('checkin');
    const checkout = document.getElementById('checkout');

    if(checkin) {
        checkin.addEventListener('click', function() {
            doCheckin(checkin, checkout);
        });
    }

    if(checkout) {
        checkout.addEventListener('click', function() {
            doCheckout(checkout);
        });
    }
});

function showLoading(button) {
    $(button).prepend('<span class="spinner-grow me-1" role="status" aria-hidden="true"></span>');
    $(button).attr('disabled', true);
}

function hideLoading(button) {
    $(button).find('.spinner-grow').remove();
}

function doCheckin(button, checkout) {
    showLoading(button);
    axios({
        method: 'post',
        url: '/api/index.php?model=timecard&method=checkin',
        data: {
            owner: USER_ID
        },
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
        .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                
                if(response.data.timecard_id) {
                    $(checkout).attr('data-id', response.data.timecard_id);
                    $(checkout).attr('data-open', response.data.timecard_open);
                    $(checkout).attr('disabled', false);
                }
            } else {
                handleErrors(response.data.message_code);
                $(button).prop('disabled', false);
            }
            hideLoading(button);
        }).catch(function (error) {
            handleErrors(error);
            console.log(error, error.response);
            hideLoading(button);
            $(button).prop('disabled', false);
        });
}

function doCheckout(button) {
    const id = button.getAttribute('data-id');
    const open = button.getAttribute('data-open');
    showLoading(button);
    axios({
        method: 'post',
        url: '/api/index.php?model=timecard&method=checkout',
        data: {
            id: id,
            open: open,
            owner: USER_ID
        },
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
        .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                $(button).attr('disabled', true);

                let result = '';

                if(response.data.timecard_time) {
                    result = 'お疲れ様でした！<br>勤務時間は' + response.data.timecard_time + 'です。';
                }

                if(response.data.timecard_timeover && response.data.timecard_timeover != '0:00') {
                    result += '<br>時間外は' + response.data.timecard_timeover + 'です。';
                }
                const $p = $(`<p class="text-success mb-0">${result}</p>`);
                $('#timecard-result').html($p);
            } else {
                handleErrors(response.data.message_code);
                $(button).prop('disabled', false);
            }
            hideLoading(button);
        }).catch(function (error) {
            handleErrors(error);
            console.log(error, error.response);
            hideLoading(button);
            $(button).prop('disabled', false);
        });
}

// 今週のスケジュール
document.addEventListener('DOMContentLoaded', async function () {
    const direction = isRtl ? 'rtl' : 'ltr';
    const calendarEl = document.getElementById('calendar');
    // Calendar settings
    const calendarColors = {
        '仕事': 'primary',
        '勤怠': 'warning',
        '休日': 'danger',
        '個人': 'info',
        'その他': 'success'
    };

    let currentStart = null;

    function filterVisibleScheduleEvents(events) {
        const userId = typeof USER_ID !== 'undefined' ? String(USER_ID) : '';
        return (events || []).filter(function (event) {
            const props = event.extendedProps || {};
            if (String(props.public_level) === '1') {
                return String(props.owner || '') === userId;
            }
            return true;
        });
    }

    async function getEventList(start, end){
    eventList = [];
        const response = await axios.get(`/api/index.php?model=schedule&method=get_event&start=${start}&end=${end}&isTop=1`);
        // check if the response is successful
        if (response.status !== 200 || !response.data) {
            handleErrors(response.data);
        }
        return filterVisibleScheduleEvents(response.data);
    }

    async function fetchEvents(info, successCallback) {
      if (currentStart === null || moment(info.start).format('YYYY-MM-DD') !== currentStart) {
        currentStart = moment(info.start).format('YYYY-MM-DD');
        currentEvents = await getEventList(currentStart, moment(info.end).format('YYYY-MM-DD'));
      }
      successCallback(currentEvents);
    }

    let calendar = new Calendar(calendarEl, {
        locale: 'ja',
        initialView: 'listWeek',
        events: fetchEvents,
        plugins: [listPlugin],
        editable: false,
        dragScroll: true,
        dayMaxEvents: 4,
        defaultAllDay: true,
       
        firstDay: 1,
        headerToolbar: {
          start: 'title',
          end: 'prev,next, schedulePage'
        },
        titleFormat: function(date) {
          if (date.start && date.end) {
            // listWeekやweek view
            const start = moment(date.start).format('M月D日');
            // FullCalendarのendは次の日の0時なので1日前にする
            const end = moment(date.end).subtract(1, 'days').format('M月D日');
            return `スケジュール: ${start} ～ ${end}`;
          } else if (date.date) {
            // day view
            return moment(date.date).format('YYYY年M月D日');
          } else if (date.start) {
            // month view
            return moment(date.start).format('YYYY年M月');
          }
          return '';
        },
        customButtons: {
          schedulePage: {
            text: 'もっと見る',
            click: function() {
              window.location.href = '/schedule';
            }
          }
        },
        allDayText: '終日',
        noEventsText: '予定がありません',
        buttonText: {
          today: '今日',
          month: '月',
          week: '週',
          day: '日',
        },
        direction: direction,
        initialDate: new Date(),
        showNonCurrentDates: false,
        //set height
        height: '400px',
        // navLinks: true, // can click day/week names to navigate views
        eventClassNames: function ({ event: calendarEvent }) {
          const colorName = calendarColors[calendarEvent._def.extendedProps.calendar];
          // Background Color
          return ['bg-label-' + colorName];
        },
        
        eventDidMount: function(info) {
            if ((info.view.type === 'listWeek' || info.view.type === 'listMonth') && info.event.allDay) {
                const eventTitle = String(info.event.title || '');
                let allDayLabel = '';
                if (eventTitle.includes('午前休')) {
                    allDayLabel = '午前休';
                } else if (eventTitle.includes('午後休')) {
                    allDayLabel = '午後休';
                }
                if (allDayLabel) {
                    const timeEl = info.el.querySelector('.fc-list-event-time');
                    if (timeEl) {
                        timeEl.textContent = allDayLabel;
                    }
                }
            }
            // Add Bootstrap tooltip for event comment
            const eventEl = info.el;
            const comment = info.event.extendedProps.comment || '';
            if(comment) {
                // Replace newline characters with <br> for HTML line breaks
                const formattedComment = comment.replace(/\n/g, '<br>');
                eventEl.setAttribute('data-bs-toggle', 'tooltip');
                eventEl.setAttribute('data-bs-placement', 'top');
                eventEl.setAttribute('data-bs-custom-class', 'tooltip-dark');
                eventEl.setAttribute('title', formattedComment);
                // Initialize new tooltip
                const tooltipInstance = new bootstrap.Tooltip(eventEl, { html: true });

                // Hide other tooltips when this one is shown
                eventEl.addEventListener('show.bs.tooltip', function () {
                    // Remove all .tooltip elements except this tooltip
                    document.querySelectorAll('.tooltip').forEach(function(tooltipEl) {
                        // Find the tooltip instance for this event element
                        const thisTooltip = bootstrap.Tooltip.getInstance(eventEl);
                        // The tooltip element for this event
                        const thisTooltipEl = thisTooltip && thisTooltip._element ? thisTooltip._element : eventEl;
                        // If this .tooltip is not the one for this event, remove it
                        if (!eventEl.contains(tooltipEl) && tooltipEl !== thisTooltipEl) {
                            tooltipEl.parentNode && tooltipEl.parentNode.removeChild(tooltipEl);
                        }
                    });
                });
            }
        },
        viewDidMount: function () {
            modifySchedulePageButton();
          },
        eventContent: function(arg) {
            const contentEl = document.createElement('div');

            const titleRow = document.createElement('div');
            titleRow.className = 'd-inline-flex align-items-center flex-wrap gap-1';

            if (String(arg.event.extendedProps.public_level) === '1') {
                const badge = document.createElement('span');
                badge.className = 'badge badge-pill bg-label-warning schedule-private-badge';
                badge.textContent = '非公開';
                titleRow.appendChild(badge);
            }

            const titleEl = document.createElement('span');
            titleEl.textContent = arg.event.title;
            titleRow.appendChild(titleEl);
            contentEl.appendChild(titleRow);

            let comment = arg.event.extendedProps.comment || '';
            if (comment.length > 20) {
                comment = comment.substring(0, 20) + '...';
            }
            if (comment) {
                const commentEl = document.createElement('div');
                commentEl.innerHTML = `<small>${comment}</small>`;
                contentEl.appendChild(commentEl);
            }

            return { domNodes: [contentEl] };
        },
      });
  
  
      
    // Render calendar
    calendar.render();
    modifySchedulePageButton();

    function modifySchedulePageButton() {
        const schedulePageButton = document.querySelector('.fc-schedulePage-button');
        if (!schedulePageButton) {
            return;
        }
        schedulePageButton.classList.remove('fc-button-primary');
        schedulePageButton.classList.remove('fc-button');
        schedulePageButton.classList.add('btn', 'btn-primary', 'btn-sm');
    }

    // 休暇スケジュール（groupware 外部 API: get_dayoff_all_api）
    const dayoffCalendarEl = document.getElementById('dayoff-calendar');
    if (dayoffCalendarEl && typeof DayoffEvents !== 'undefined') {
        async function fetchDayoffEvents(info, successCallback) {
            const events = await DayoffEvents.fetchDayoffEventsForRange(info.start, info.end);
            successCallback(events);
        }

        const dayoffCalendar = new Calendar(dayoffCalendarEl, {
            locale: 'ja',
            initialView: 'listWeek',
            events: fetchDayoffEvents,
            plugins: [listPlugin],
            editable: false,
            dragScroll: true,
            dayMaxEvents: 4,
            firstDay: 1,
            headerToolbar: {
                start: 'title',
                end: 'prev,next'
            },
            titleFormat: function (date) {
                if (date.start && date.end) {
                    const start = moment(date.start).format('M月D日');
                    const end = moment(date.end).subtract(1, 'days').format('M月D日');
                    return 'CAILY休暇: ' + start + ' ～ ' + end;
                }
                if (date.date) {
                    return 'CAILY休暇: ' + moment(date.date).format('YYYY年M月D日');
                }
                if (date.start) {
                    return 'CAILY休暇: ' + moment(date.start).format('YYYY年M月');
                }
                return '';
            },
            allDayText: '全休',
            noEventsText: '休暇予定がありません',
            buttonText: {
                today: '今日',
                month: '月',
                week: '週',
                day: '日'
            },
            direction: direction,
            initialDate: new Date(),
            showNonCurrentDates: false,
            height: '400px',
            eventClassNames: function ({ event: calendarEvent }) {
                const calendarType = calendarEvent.extendedProps.calendar || '勤怠';
                const colorName = calendarColors[calendarType] || calendarColors['勤怠'];
                return ['bg-label-' + colorName];
            },
            eventDidMount: function (info) {
                if (info.view.type !== 'listWeek' && info.view.type !== 'listMonth') {
                    return;
                }
                const timeEl = info.el.querySelector('.fc-list-event-time');
                if (!timeEl) {
                    return;
                }
                if (info.event.allDay) {
                    timeEl.textContent = '全休';
                } else {
                    const timeLabel = info.event.extendedProps.timeLabel;
                    if (timeLabel) {
                        timeEl.textContent = timeLabel;
                    }
                }
            }
        });

        dayoffCalendar.render();
    }


    // 勤怠統計
    const generateStatisticButton = document.getElementById('generate-statistic');
    if(generateStatisticButton) {
        generateStatisticButton.addEventListener('click', function() {
            generateStatistic();
        });
    }
    function generateStatistic() {
        showLoading(generateStatisticButton);
        axios.get('/api/index.php?model=timecard&method=generateStatistic')
            .then(function (response) {
                hideLoading(generateStatisticButton);
                handleSuccess(response.data.message_code);
                $(generateStatisticButton).prop('disabled', false);
                changeStatistic();
            })
            .catch(function (error) {
                handleErrors(error);
                console.log(error, error.response);
                hideLoading(generateStatisticButton);
                $(generateStatisticButton).prop('disabled', false);
            });
    }

    // 勤怠統計
    let barChart = null;
    let memberList = [];
    const memberListSelect = document.getElementById('timecard-statistic-select');
    const statistic = document.getElementById('timecard-statistic');
    
    async function getMember() {
        memberList = [];
        const response = await axios.get(`/api/index.php?model=member&method=get_member`);
        // check if the response is successful
        if (response.status !== 200 || !response.data || !response.data.list) {
          handleErrors(response.data);
          return [];
        }
        memberList = response.data.list;
        memberList = memberList.filter(member => member.group_name != '退職者');
        return memberList;
    }
    
    // Chỉ khởi tạo nếu elements tồn tại
    if (memberListSelect && statistic) {
        async function generateStatisticTimecard() {
            try {
                const memberList = await getMember();
                
                memberListSelect.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'すべて';
                memberListSelect.appendChild(option);
                memberList.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.userid;
                    option.textContent = member.realname;
                    memberListSelect.appendChild(option);
                });
                changeStatistic();
            } catch (error) {
                console.error('Error generating statistic timecard:', error);
            }
        }

        memberListSelect.addEventListener('change', function() {
            changeStatistic();
        });

        function changeStatistic() {
            if (!memberListSelect) return;
            const type = 'timecard_all';
            const scope = 'monthly';
            const time = moment().format('YYYY-MM');
            const userid = memberListSelect.value || '';
            axios.get('/api/index.php?model=timecard&method=getStatistic&type=' + type + '&scope=' + scope + '&time=' + time + '&userid=' + userid)
                .then(function (response) {
                    if(response.data && response.data.list) {
                        const data = response.data.list;
                        generateStatisticChart(data);
                    } else{
                        generateStatisticChart([]);
                    }
                })
                .catch(function (error) {
                    console.error('Error loading statistic:', error);
                    handleErrors(error);
                    if (statistic) {
                        statistic.innerHTML = '<p class="text-danger text-center py-4">データの読み込みに失敗しました</p>';
                    }
                });
        }

        generateStatisticTimecard();
    }

    function generateStatisticChart(data) {
        // Kiểm tra element tồn tại
        if (!statistic) {
            console.error('timecard-statistic element not found');
            return;
        }
        
        if(data.length == 0) {
            if(barChart) {
                barChart.updateSeries([]);
            }
            // Hiển thị message khi không có data
            statistic.innerHTML = '<p class="text-muted text-center py-4">データがありません</p>';
            return;
        }
        // create new chart
        var catLabel = {
            'timecard_time': '勤務時間',
            'timecard_timeover': '時間外',
            'timecard_timeholiday': '休日出勤',
            'timecard_total': '合計'
        };
        const updated = document.getElementById('timecard-statistic-updated');

        // First, sort the list by time in ascending order
        const sortedList = data.sort((a, b) => new Date(a.time) - new Date(b.time));
        if (updated && sortedList.length > 0 && sortedList[0].updated) {
            updated.innerHTML = '更新時間: ' + moment(sortedList[0].updated).format('YYYY-MM-DD HH:mm');
        }

        // // Get unique keys from value objects (timecard_time, timecard_timeover)
        const valueKeys = [...new Set(sortedList.map(item => 
        {
            let value = {};
            try {
                const decodedValue = item.value.replace(/&quot;/g, '"');
                value = JSON.parse(decodedValue);
            } catch (e) {
                console.error('Failed to parse JSON:', item.value);
                return {};
            }
            return Object.keys(value);
        }
        ))].flat();
        // remove duplicate keys
        const uniqueValueKeys = [...new Set(valueKeys)];

        const categories = sortedList.map(item => item.name);

        const series = uniqueValueKeys.map(key => {
            const label = catLabel[key] || key;
            return {
                name: label,
                data: sortedList.map(item => {
                    try {
                        const decodedValue = item.value.replace(/&quot;/g, '"');
                        const valueObj = JSON.parse(decodedValue);
                        const value = valueObj[key];
                        if (!value) return 0;
                        // Convert time format (HH:mm) to decimal hours for chart
                        if (typeof value === 'string' && value.includes(':')) {
                            const [hours, minutes] = value.split(':').map(Number);
                            return parseFloat((hours + minutes / 60).toFixed(1));
                        } else if (typeof value === 'number') {
                            return parseFloat(value.toFixed(1));
                        }
                        return 0;
                    } catch (e) {
                        console.error('Error parsing value for chart:', e, item);
                        return 0;
                    }
                })
            };
        }).filter(s => s.name); // Loại bỏ series không có label
     
        // Define explicit colors instead of using config references
        const chartColors = ['#4e73df', '#f6c23e', '#e74a3b', '#36b9cc', '#1cc88a'];
        const borderColor = 'rgba(224,224,224,0.2)';
        const labelColor = '#ccc';

        const barChartEl = statistic,
            barChartConfig = {
                chart: {
                    height: 400,
                    type: 'bar',
                    stacked: false,
                    parentHeightOffset: 0,
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: true,
                            zoom: true,
                            zoomin: true,
                            zoomout: true,
                            pan: true,
                            reset: true
                        },
                        export: {
                            svg: {
                                filename: 'timecard-chart-svg',
                            },
                            png: {
                                filename: 'timecard-chart-png',
                            },
                            csv: {
                                filename: 'timecard-chart-data',
                                columnDelimiter: ',',
                                headerCategory: 'Month',
                                headerValue: 'Value'
                            }
                        }
                    }
                },
                plotOptions: {
                    bar: {
                        columnWidth: '40%',
                    }
                },
                dataLabels: {
                    enabled: false
                },
                legend: {
                    show: true,
                    position: 'top',
                    horizontalAlign: 'start',
                    labels: {
                        colors: labelColor,
                        useSeriesColors: false
                    },
                },
                colors: chartColors, // Use explicit array
                stroke: {
                    show: true,
                    colors: ['transparent']
                },
                grid: {
                    borderColor: borderColor,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                series: series,
                tooltip: {
                    enabled: true,
                    shared: true,
                    intersect: false,
                    custom: function({series, seriesIndex, dataPointIndex, w}) {
                        // Calculate total of all series at this data point
                        let total = 0;
                        series.forEach(s => {
                            // Make sure we're adding a number
                            const value = parseFloat(s[dataPointIndex] || 0);
                            if (!isNaN(value)) {
                                total += value;
                            }
                        });
                        
                        // Build tooltip content
                        let content = '<div class="apexcharts-tooltip-title" style="font-weight:bold;margin-bottom:5px;">' + 
                                    w.globals.labels[dataPointIndex] + '</div>';
                        
                        // Add each series
                        var count = 0;
                        series.forEach((s, i) => {
                            const value = parseFloat(s[dataPointIndex] || 0);
                            if (!isNaN(value)) {
                                content += '<div class="apexcharts-tooltip-series-group" style="display:flex;align-items:center;margin:4px 0;">' +
                                        '<span class="apexcharts-tooltip-marker" style="display:inline-block;width:10px;height:10px;margin-right:5px;border-radius:50%;background-color:' + 
                                        w.globals.colors[i] + '"></span>' +
                                        '<span class="apexcharts-tooltip-text">' + 
                                        w.globals.seriesNames[i] + ': ' + value.toFixed(1) + 'h</span></div>';
                                count++;
                            }
                        });
                        
                        // For the total row, use a fixed color like #777 instead of dynamic color
                        content += '<div class="apexcharts-tooltip-series-group" style="display:flex;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid #e0e0e0;">' +
                                '<span class="apexcharts-tooltip-marker" style="display:inline-block;width:10px;height:10px;margin-right:5px;border-radius:50%;background-color:#777"></span>' +
                                '<span class="apexcharts-tooltip-text" style="font-weight:bold;">' + 
                                '合計: ' + total.toFixed(1) + 'h</span></div>';
                        
                        return content;
                    }
                },
                
                xaxis: {
                    categories: categories,
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: {
                        style: {
                            colors: labelColor,
                            fontSize: '13px'
                        }
                    }
                },
                yaxis: {
                    min: 0,
                    tickAmount: 10,
                    labels: {
                        style: {
                            colors: labelColor,
                            fontSize: '13px'
                        }
                    }
                },
                fill: {
                    opacity: 1
                }
            };
        
        // Kiểm tra element và series có data không
        if (!barChartEl) {
            console.error('Chart element not found');
            return;
        }
        
        if (!series || series.length === 0) {
            console.warn('No series data to display');
            statistic.innerHTML = '<p class="text-muted text-center py-4">データがありません</p>';
            return;
        }
        
        try {
            if (barChart === null) {
                barChart = new ApexCharts(barChartEl, barChartConfig);
                barChart.render();
            } else {
                barChart.updateOptions(barChartConfig);
            }
        } catch (error) {
            console.error('Error rendering chart:', error);
            statistic.innerHTML = '<p class="text-danger text-center py-4">グラフの表示に失敗しました</p>';
        }
    }

    // Project Statistics
    async function loadProjectStats() {
        const departmentId = document.getElementById('project-stats-department').value;
        
        try {
            
            // Load data using main department filter
            const response = await fetch(`/api/index.php?model=project&method=getDashboardStats&department_id=${departmentId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            
            // Check for backend error
            if (data.error) {
                console.error('Backend error:', data.error);
                return;
            }
            
            // Render all components with the same data
            if (data && typeof data === 'object') {
                renderProjectStats(data);
            } else {
                console.error('Failed to load project stats: Invalid data format', data);
            }
            
        } catch (error) {
            console.error('Error loading project stats:', error);
        }
    }

    function renderProjectStats(stats) {
        console.log('Rendering project stats:', stats); // Debug log
        
        // Overview cards
        if (stats.overview) {
            const totalElement = document.getElementById('total-projects');
            const activeElement = document.getElementById('active-projects');
            const completedElement = document.getElementById('completed-projects');
            const newElement = document.getElementById('new-this-month');
            
            if (totalElement) totalElement.textContent = stats.overview.total || 0;
            if (activeElement) activeElement.textContent = stats.overview.active || 0;
            if (completedElement) completedElement.textContent = stats.overview.completed || 0;
            if (newElement) newElement.textContent = stats.new_this_month || 0;
        }

        // Monthly stats chart
        if (stats.monthly_stats && stats.monthly_stats.length > 0) {
            renderMonthlyStats(stats.monthly_stats);
        }

        // Financial by department chart
        if (stats.financial_by_department && stats.financial_by_department.length > 0) {
            renderFinancialByDepartment(stats.financial_by_department);
        }
    }

    function renderMonthlyStats(monthlyData) {
        const months = monthlyData.map(item => {
            const [year, month] = item.month.split('-');
            return `${year}年${month}月`;
        });

        // Use same colors as timecard chart
        const chartColors = ['#4e73df', '#f6c23e', '#e74a3b', '#36b9cc', '#1cc88a'];
        const borderColor = 'rgba(224,224,224,0.2)';
        const labelColor = '#ccc';

        const options = {
            series: [
                {
                    name: '総案件数',
                    data: monthlyData.map(item => parseInt(item.total_projects))
                }
            ],
            chart: {
                type: 'bar',
                height: 350,
                stacked: false,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    dataLabels: {
                        position: 'top'
                    },
                    columnWidth: '30px',
                    maxWidth: '30px'
                }
            },
            colors: chartColors,
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val;
                },
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: [labelColor]
                }
            },
            grid: {
                borderColor: borderColor,
                xaxis: {
                    lines: {
                        show: true
                    }
                }
            },
            xaxis: {
                categories: months,
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    },
                    rotate: -45,
                    rotateAlways: false
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    }
                }
            },
            tooltip: {
                shared: false,
                y: {
                    formatter: function (val) {
                        return val + ' 件';
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px',
                labels: {
                    colors: labelColor,
                    useSeriesColors: false
                }
            }
        };

        if (window.monthlyChart) {
            window.monthlyChart.destroy();
        }

        const chartElement = document.getElementById('monthly-stats-chart');
        if (chartElement) {
            window.monthlyChart = new ApexCharts(chartElement, options);
            window.monthlyChart.render();
        }
    }

    function renderFinancialByDepartment(financialData) {
        // Group data by department
        const departmentGroups = {};
        financialData.forEach(item => {
            if (!departmentGroups[item.department_name]) {
                departmentGroups[item.department_name] = [];
            }
            departmentGroups[item.department_name].push({
                month: item.month,
                total_amount: parseFloat(item.total_amount) || 0,
                pending_estimates: parseFloat(item.pending_estimates) || 0,
                pending_invoices: parseFloat(item.pending_invoices) || 0,
                project_count: parseInt(item.project_count) || 0
            });
        });

        // Create chart data
        const months = [...new Set(financialData.map(item => item.month))].sort();
        const departments = Object.keys(departmentGroups);

        // Calculate total for all departments
        const totalByMonth = {};
        months.forEach(month => {
            totalByMonth[month] = financialData
                .filter(item => item.month === month)
                .reduce((sum, item) => sum + (parseFloat(item.total_amount) || 0), 0);
        });

        const series = [
            {
                name: 'すべての部署',
                data: months.map(month => totalByMonth[month])
            },
            ...departments.map(dept => ({
                name: dept,
                data: months.map(month => {
                    const deptData = departmentGroups[dept].find(item => item.month === month);
                    return deptData ? deptData.total_amount : 0;
                })
            }))
        ];

        // Use same colors as timecard chart
        const chartColors = ['#4e73df', '#f6c23e', '#e74a3b', '#36b9cc', '#1cc88a', '#7367f0', '#28c76f', '#ff9f43'];
        const borderColor = 'rgba(224,224,224,0.2)';
        const labelColor = '#ccc';

        const options = {
            series: series,
            chart: {
                type: 'line',
                height: 350,
                stacked: false,
                toolbar: {
                    show: false
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            colors: chartColors,
            dataLabels: {
                enabled: false
            },
            grid: {
                borderColor: borderColor,
                xaxis: {
                    lines: {
                        show: true
                    }
                }
            },
            xaxis: {
                categories: months.map(month => {
                    const [year, monthNum] = month.split('-');
                    return `${year}年${monthNum}月`;
                }),
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    },
                    rotate: -45,
                    rotateAlways: false
                }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    },
                    style: {
                        colors: labelColor,
                        fontSize: '12px'
                    }
                }
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val) {
                        return formatCurrency(val);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px',
                labels: {
                    colors: labelColor,
                    useSeriesColors: false
                }
            }
        };

        if (window.financialChart) {
            window.financialChart.destroy();
        }

        const chartElement = document.getElementById('financial-chart');
        if (chartElement) {
            try {
                window.financialChart = new ApexCharts(chartElement, options);
                window.financialChart.render();
            } catch (error) {
                console.error('Error rendering financial chart:', error);
            }
        }

    }

    function formatCurrency(amount) {
        if (!amount || amount === 0) return '¥0';
        return '¥' + parseInt(amount).toLocaleString();
    }

    // Load project stats if user has permission
    if (document.getElementById('project-stats-section')) {
        // Load departments for dropdown
        loadDepartmentsForStats().then(() => {
            // Load initial stats after departments are loaded
            loadProjectStats();
        });
    }

    // Load departments for project stats dropdown
    async function loadDepartmentsForStats() {
        try {
            const response = await fetch('/api/index.php?model=department&method=listByUser');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            // Populate main department dropdown only
            const select = document.getElementById('project-stats-department');
            if (select && data) {
                // Clear existing options except the first one
                select.innerHTML = '<option value="">すべての部署</option>';
                
                // Add department options
                data.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = dept.name;
                    select.appendChild(option);
                });
                
                // Remove existing event listeners to avoid duplicates
                const newSelect = select.cloneNode(true);
                select.parentNode.replaceChild(newSelect, select);
                
                // Add event listener for department change
                newSelect.addEventListener('change', function() {
                    loadProjectStats();
                });
            }
            
            return Promise.resolve();
        } catch (error) {
            console.error('Error loading departments for stats:', error);
            return Promise.reject(error);
        }
    }

});