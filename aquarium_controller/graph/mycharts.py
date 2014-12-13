from reportlab.graphics.shapes import Drawing, String, Rect
from reportlab.graphics.charts.lineplots import LinePlot
from reportlab.graphics.charts.lineplots import ScatterPlot
from reportlab.lib import colors
from reportlab.graphics.charts.legends import Legend
from reportlab.graphics.charts.textlabels import Label
from reportlab.graphics.widgets.markers import makeMarker


class MyLineChartDrawing(Drawing):
    def __init__(self, width=600, height=400, *args, **kw):
        Drawing.__init__(self, *args, **kw)
        #apply(Drawing.__init__,(self,width,height)+args,kw)
        backcolor = colors.HexColor('#010101')

        self.add(Rect(0, 0, self.width, self.height, fillColor=backcolor))

        self.add(LinePlot(), name='chart')

        self.add(String(200,180,'Plot'), name='title')

        #set any shapes, fonts, colors you want here.  We'll just
        #set a title font and place the chart within the drawing.
        #pick colors for all the lines, do as many as you need
        self.chart.x = 40
        self.chart.y = 30
        self.chart.width = self.width - 45
        self.chart.height = self.height - 35
        self.chart.lines[0].strokeColor = colors.blue
        self.chart.lines[1].strokeColor = colors.green
        self.chart.lines[2].strokeColor = colors.yellow
        self.chart.lines[3].strokeColor = colors.red
        self.chart.lines[4].strokeColor = colors.black
        self.chart.lines[5].strokeColor = colors.orange
        self.chart.lines[6].strokeColor = colors.cyan
        self.chart.lines[7].strokeColor = colors.magenta
        self.chart.lines[8].strokeColor = colors.brown
	
        #self.chart.fillColor = colors.black
        self.title.fontName = 'Times-Roman'
        self.title.fontSize = 18
        self.title.fillColor = colors.white
        self.chart.data = [((0, 50), (100,100), (200,200), (250,210), (300,300), (400,500))]
        self.chart.xValueAxis.labels.fontSize = 12
        self.chart.xValueAxis.forceZero = 0
        #self.chart.xValueAxis.gridEnd = 115
        self.chart.xValueAxis.tickDown = 3
        self.chart.xValueAxis.labelTextFormat = '%2.0f'
        #self.chart.xValueAxis.visibleGrid = 1
        self.chart.xValueAxis.strokeColor = colors.white
        self.chart.xValueAxis.labels.fillColor = colors.white
        self.chart.yValueAxis.tickLeft = 3
        self.chart.yValueAxis.labels.fontName = 'Times-Roman'
        self.chart.yValueAxis.labels.fontSize = 12
        self.chart.yValueAxis.strokeColor = colors.white
        self.chart.yValueAxis.labels.fillColor = colors.white
        self.chart.yValueAxis.labelTextFormat = '%3.2f'
        #self.chart.yValueAxis.valueMax = 1
        self.title.x = self.width/2
        self.title.y = 0
        self.title.textAnchor ='middle'
        self.add(Legend(),name='Legend')
        self.Legend.fontName = 'Times-Roman'
        self.Legend.fontSize = 12
        self.Legend.x = self.width - 100
        self.Legend.y = 85
        self.Legend.dxTextSpace = 5
        self.Legend.dy = 5
        self.Legend.dx = 5
        self.Legend.deltay = 5
        self.Legend.alignment ='right'
        self.Legend.fillColor = colors.white
        self.add(Label(),name='XLabel')
        self.XLabel.fontName = 'Times-Roman'
        self.XLabel.fontSize = 12
        self.XLabel.x = 85
        self.XLabel.y = 5
        self.XLabel.textAnchor ='middle'
        self.XLabel.fillColor = colors.white
        #self.XLabel.height = 20
        self.XLabel._text = ""
        self.add(Label(),name='YLabel')
        self.YLabel.fontName = 'Times-Roman'
        self.YLabel.fontSize = 12
        self.YLabel.x = 2
        self.YLabel.y = 80
        self.YLabel.angle = 90
        self.YLabel.textAnchor ='middle'
        self.YLabel.fillColor = colors.white
        self.YLabel._text = ""
        self.chart.yValueAxis.forceZero = 1
        self.chart.xValueAxis.forceZero = 1
