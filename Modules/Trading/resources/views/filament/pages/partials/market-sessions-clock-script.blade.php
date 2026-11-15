window.ffMarketSessionsClock = function (payload) {
    return {
        sessions: payload.sessions ?? [],
        localClock: payload.local_clock ?? '--:--',
        localClockShort: payload.local_clock_short ?? '--:--',
        nowPercent: Number(payload.now_percent ?? 0),
        currentPktHour: Number(payload.current_pkt_hour ?? 0),
        activeKeys: Array.isArray(payload.active_keys) ? payload.active_keys : [],
        activeNames: Array.isArray(payload.active_names) ? payload.active_names : [],
        overlapStatus: payload.overlap_status ?? '',
        forexWeekOpen: Boolean(payload.forex_week_open),
        trackLabel: payload.track_label ?? (payload.none_active ?? ''),
        timer: null,
        labels: {
            statusClosed: payload.status_closed ?? '',
            statusIdle: payload.status_idle ?? '',
            statusSingle: payload.status_single ?? '',
            statusOverlap: payload.status_overlap ?? '',
            activePrefix: payload.active_prefix ?? '',
            noneActive: payload.none_active ?? '',
        },

        init() {
            this.tick()
            this.timer = setInterval(() => this.tick(), 1000)
        },

        destroy() {
            if (this.timer) {
                clearInterval(this.timer)
            }
        },

        tick() {
            const now = new Date()
            const pkt = this.pktParts(now)

            this.localClock = `${pkt.hh}:${pkt.mm}:${pkt.ss}`
            this.localClockShort = `${pkt.hh}:${pkt.mm}`
            this.nowPercent = (pkt.minutes / 1440) * 100
            this.currentPktHour = pkt.hour
            this.forexWeekOpen = this.isForexWeekOpen(now)

            if (! this.forexWeekOpen) {
                this.activeKeys = []
                this.activeNames = []
                this.overlapStatus = this.labels.statusClosed
                this.trackLabel = this.labels.noneActive

                return
            }

            const utcMinutes = (now.getUTCHours() * 60) + now.getUTCMinutes() + (now.getUTCSeconds() / 60)
            const active = this.sessions.filter((session) => this.isOpen(utcMinutes, session.open_utc, session.close_utc))
            this.activeKeys = active.map((session) => session.key)
            this.activeNames = active.map((session) => session.name)
            this.overlapStatus = this.buildOverlapStatus(this.activeNames)
            this.trackLabel = this.activeNames.length
                ? `${this.labels.activePrefix} ${this.activeNames.join(', ')}`
                : this.labels.noneActive
        },

        pktParts(now) {
            const pkt = new Date(now.getTime() + (5 * 60 * 60 * 1000))
            const hour = pkt.getUTCHours()
            const minute = pkt.getUTCMinutes()
            const second = pkt.getUTCSeconds()

            return {
                hour,
                hh: String(hour).padStart(2, '0'),
                mm: String(minute).padStart(2, '0'),
                ss: String(second).padStart(2, '0'),
                minutes: (hour * 60) + minute + (second / 60),
            }
        },

        buildOverlapStatus(names) {
            if (names.length === 0) {
                return this.labels.statusIdle
            }

            if (names.length === 1) {
                return this.labels.statusSingle.replace(':name', names[0])
            }

            return this.labels.statusOverlap
                .replace(':count', String(names.length))
                .replace(':names', names.join(' · '))
        },

        isForexWeekOpen(now) {
            const day = now.getUTCDay()
            const minutes = (now.getUTCHours() * 60) + now.getUTCMinutes()
            const fridayClose = 22 * 60
            const sundayOpen = 22 * 60

            if (day === 6) {
                return false
            }

            if (day === 0) {
                return minutes >= sundayOpen
            }

            if (day === 5) {
                return minutes < fridayClose
            }

            return true
        },

        isOpen(nowMinutes, openUtc, closeUtc) {
            if (openUtc === closeUtc) {
                return false
            }

            if (openUtc < closeUtc) {
                return nowMinutes >= openUtc && nowMinutes < closeUtc
            }

            return nowMinutes >= openUtc || nowMinutes < closeUtc
        },
    }
}
